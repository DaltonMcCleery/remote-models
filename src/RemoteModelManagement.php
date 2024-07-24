<?php

namespace RemoteModels;

use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

trait RemoteModelManagement
{
    protected static $remoteModelConnection;

    public static function bootRemoteModelManagement(): void
    {
        $instance = (new static);

        $cachePath = $instance->remoteModelCachePath();
        $dataPath = $instance->remoteModelCacheReferencePath();

        switch (true) {
            case \file_exists($cachePath) && config('remote-models.cache-ttl') && cache()->missing($instance->remoteModelCacheFileName()):
            case \file_exists($cachePath) && \filemtime($dataPath) <= \filemtime($cachePath):
                // cache-file-found-and-up-to-date
                static::setSqliteConnection($cachePath);
                break;

            case \file_exists($instance->remoteModelCacheDirectory()) && \is_writable($instance->remoteModelCacheDirectory()):
                // cache-file-not-found-or-stale
                static::refreshRemoteModel();
                break;

            default:
                // no-caching-capabilities
                static::setSqliteConnection(':memory:');
                $instance->migrate();
                break;
        }
    }

    private function getRemoteModelEndpoint(): ?string
    {
        if (! $this->remoteEndpoint) {
            $this->remoteEndpoint = '';
        }

        if (Str::startsWith($this->remoteEndpoint, 'http')) {
            // Override, don't use config domain or path.
            return $this->remoteEndpoint;
        }

        $domain = config('remote-models.domain', '');
        if (Str::endsWith($domain, '/')) {
            $domain = \rtrim($domain, '/');
        }

        $path = config('remote-models.api-path');
        if (Str::endsWith($path, '/') && Str::startsWith($this->remoteEndpoint, '/')) {
            $path = \rtrim($path, '/');
        }

        if (! Str::startsWith($path, '/')) {
            $path = '/' . $path;
        }

        return $domain . $path . $this->remoteEndpoint;
    }

    private function getRemoteModelSchema(): array
    {
        return $this->remoteSchema ?? [];
    }

    protected function remoteModelCachePath(): string
    {
        return \implode(DIRECTORY_SEPARATOR, [
            $this->remoteModelCacheDirectory(),
            $this->remoteModelCacheFileName(),
        ]);
    }

    public function remoteModelCacheFileName(): string
    {
        $filename = Str::of(static::class)
            ->replace('\\', '')
            ->kebab()
            ->replace('app-models', 'model')
            ->value();

        return config('remote-models.cache-prefix', 'remote') . '-' . $filename . '.sqlite';
    }

    protected function remoteModelCacheDirectory(): false|string
    {
        return realpath(config('remote-models.cache-path') ?? storage_path('framework/cache'));
    }

    protected function remoteModelCacheReferencePath(): false|string
    {
        return (new \ReflectionClass(static::class))->getFileName();
    }

    public static function resolveConnection($connection = null)
    {
        return static::$remoteModelConnection;
    }

    protected static function setSqliteConnection($database): void
    {
        $config = [
            'driver' => 'sqlite',
            'database' => $database,
        ];

        static::$remoteModelConnection = app(ConnectionFactory::class)->make($config);

        app('config')->set('database.connections.'.static::class, $config);
    }

    public function getConnectionName(): string
    {
        return static::class;
    }

    protected function resolveRemoteModelColumnType(mixed $value): string
    {
        switch (true) {
            case \is_int($value):
                return 'integer';
            case \is_numeric($value):
                return 'float';
            case \is_array($value):
                if (\array_key_exists('date', $value)) {
                    return 'dateTime';
                } else {
                    return 'json';
                }
            case \is_object($value):
                if ($value instanceof \DateTime) {
                    return 'dateTime';
                } else {
                    return 'json';
                }
            case \strtotime($value) !== false:
                return 'dateTime';
            default:
                return 'string';
        }
    }

    protected static function refreshRemoteModel(): void
    {
        $instance = (new static);

        $cachePath = $instance->remoteModelCachePath();
        $dataPath = $instance->remoteModelCacheReferencePath();

        \file_put_contents($cachePath, '');
        static::setSqliteConnection($cachePath);

        $instance->migrate();

        \touch($cachePath, \filemtime($dataPath));

        if (config('remote-models.cache-ttl')) {
            $ttl = now()->add(config('remote-models.cache-ttl'));
            cache()->remember($instance->remoteModelCacheFileName(), $ttl, fn () => $ttl);
        }
    }

    protected function createRemoteModelTable(?\Closure $schemaCallback = null): void
    {
        /** @var \Illuminate\Database\Schema\SQLiteBuilder $schemaBuilder */
        $schemaBuilder = static::resolveConnection()->getSchemaBuilder();

        try {
            $schemaBuilder->create($this->getTable(), function (BluePrint $table) use ($schemaCallback) {
                $schema = $schemaCallback
                    ? $schemaCallback($this->getRemoteModelSchema())
                    : $this->getRemoteModelSchema();

                // If no custom schema was found
                // and the API did not return any values to build a schema with...
                if (\count($schema) === 0) {
                    throw new \Exception('No data returned from Remote Model `$remoteEndpoint`.');
                }

                $table->id();

                $schema = collect($schema)
                    // Filter out common columns, we'll add these by default.
                    ->filter(fn ($column, $value) => ! \in_array($column, ['id', 'created_at', 'updated_at']))
                    ->filter(fn ($column, $value) => ! \in_array($value, ['id', 'created_at', 'updated_at']))
                    ->toArray();

                foreach ($schema as $column => $value) {
                    if (\count($this->getRemoteModelSchema()) === 0) {
                        // Custom schema not provided, resolve the type by the API value given.
                        $type = $this->resolveRemoteModelColumnType($value);
                    } else {
                        $type = $value;
                    }

                    $table->{$type}($column)->nullable();
                }

                $table->timestamps();
            });
        } catch (QueryException $e) {
            if (Str::contains($e->getMessage(), [
                'already exists (SQL: create table',
                \sprintf('table "%s" already exists', $this->getTable()),
            ])) {
                // This error can happen in rare circumstances due to a race condition.
                // Concurrent requests may both see the necessary preconditions for
                // the table creation, but only one can actually succeed.
                return;
            }

            throw $e;
        }
    }

    private function insertRemoteModelData(array $data, int $chunk = 15): void
    {
        $schema = $this->getRemoteModelSchema();
        $checkSchema = \count($schema) > 0;

        foreach (\array_chunk($data, $chunk) ?? [] as $inserts) {
            if (! empty($inserts)) {
                // Inserted data must map to the provided schema, if provided
                if ($checkSchema) {
                    // Add common columns we've excluded earlier.
                    if (! isset($schema['id'])) {
                        $schema['id'] = 'integer';
                    }

                    if (! isset($schema['created_at'])) {
                        $schema['created_at'] = 'dateTime';
                    }

                    if (! isset($schema['updated_at'])) {
                        $schema['updated_at'] = 'dateTime';
                    }

                    // Remove any non-mapped columns from the data that do not appear in the schema.
                    $inserts = collect($inserts)
                        ->map(fn ($insertData) => collect($insertData)->intersectByKeys($schema)->toArray())
                        ->toArray();
                }

                static::insert(
                    collect($inserts)
                        ->map(fn ($insertData) => collect($insertData)->map(function ($value) {
                            switch (true) {
                                case \is_array($value) && \array_key_exists('date', $value):
                                    return new \DateTime($value['date'], \array_key_exists('timezone', $value) ? new \DateTimeZone($value['timezone']) : null);
                                case ! \is_array($value) && \strtotime($value) !== false:
                                    return new \DateTime($value);
                                default:
                                    return $value;
                            }
                        }))
                        ->toArray()
                );
            }
        }
    }
}
