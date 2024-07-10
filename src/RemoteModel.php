<?php

namespace RemoteModels;

use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

trait RemoteModel
{
	protected static $remoteModelConnection;

    public static function bootRemoteModel(): void
    {
        $instance = (new static);

        $cachePath = $instance->remoteModelCachePath();
        $dataPath = $instance->remoteModelCacheReferencePath();

        $states = [
            'cache-file-found-and-up-to-date' => function () use ($cachePath) {
                static::setSqliteConnection($cachePath);
            },
            'cache-file-not-found-or-stale' => function () use ($cachePath, $dataPath, $instance) {
                static::cacheFileNotFoundOrStale($cachePath, $dataPath, $instance);
            },
            'no-caching-capabilities' => function () use ($instance) {
                static::setSqliteConnection(':memory:');

                $instance->migrate();
            },
        ];

        switch (true) {
            case \file_exists($cachePath) && \filemtime($dataPath) <= \filemtime($cachePath):
                $states['cache-file-found-and-up-to-date']();
                break;

            case \file_exists($instance->remoteModelCacheDirectory()) && \is_writable($instance->remoteModelCacheDirectory()):
                $states['cache-file-not-found-or-stale']();
                break;

            default:
                $states['no-caching-capabilities']();
                break;
        }
    }

    public function getEndpoint(): ?string
    {
        if (! $this->remoteEndpoint) {
            $this->remoteEndpoint = '/' . Str::of($this::class)
                ->afterLast('\\')
                ->kebab()
                ->value();
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

    public function getSchema(): array
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

	protected function remoteModelCacheFileName(): string
    {
		return config('remote-models.cache-prefix', 'remote').'-'.Str::kebab(\str_replace('\\', '', static::class)).'.sqlite';
	}

	protected function remoteModelCacheDirectory(): false|string
    {
		return realpath(config('remote-models.cache-path', storage_path('framework/cache')));
	}

    protected function remoteModelCacheReferencePath(): false|string
    {
        return (new \ReflectionClass(static::class))->getFileName();
    }

	protected static function cacheFileNotFoundOrStale($cachePath, $dataPath, $instance): void
    {
		\file_put_contents($cachePath, '');

		static::setSqliteConnection($cachePath);

		$instance->migrate();

		\touch($cachePath, \filemtime($dataPath));
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

	public function migrate(): void
    {
        /** @var \Illuminate\Database\Schema\SQLiteBuilder $schemaBuilder */
        $schemaBuilder = static::resolveConnection()->getSchemaBuilder();

        try {
            $schemaBuilder->create($this->getTable(), function (BluePrint $table) {
                $schema = $this->getSchema();

                // If no schema given, load initial data from endpoint to gather columns.
                if (\count($schema) === 0) {
                    $response = $this->callRemoteModelEndpoint();

                    if (\array_key_exists('data', $response)) {
                        // Paginated
                        if (\count($response['data']) > 0) {
                            $schema = $response['data'][0];
                        }
                    } else if (\count($response) > 0) {
                        // Default to array of data.
                        $schema = $response[0];
                    }
                }

                // If no custom schema was found
                // and the API did not return any values to build a schema with...
                if (\count($schema) === 0) {
                    throw new \Exception('No data returned from Remote Model `$endpoint`.');
                }

                $table->id();

                $schema = collect($schema)
                    // Filter out common columns, we'll add these by default.
                    ->filter(fn ($column, $value) => ! \in_array($column, ['id', 'created_at', 'updated_at']))
                    ->filter(fn ($column, $value) => ! \in_array($value, ['id', 'created_at', 'updated_at']))
                    ->toArray();

                foreach ($schema as $column => $value) {
                    if (\gettype($column) !== 'integer') {
                        // Custom schema not provided, resolve the type by the API value given.
                        switch (true) {
                            case \is_int($value):
                                $type = 'integer';
                                break;
                            case \is_numeric($value):
                                $type = 'float';
                                break;
                            case \is_array($value):
                                if (\array_key_exists('date', $value)) {
                                    $type = 'dateTime';
                                } else {
                                    $type = 'json';
                                }
                                break;
                            case \is_object($value):
                                if ($value instanceof \DateTime) {
                                    $type = 'dateTime';
                                } else {
                                    $type = 'json';
                                }
                                break;
                            case \strtotime($value) !== false:
                                $type = 'dateTime';
                                break;
                            default:
                                $type = 'string';
                        }
                    } else {
                        $type = $value;
                    }

                    $table->{$type}($column)->nullable();
                }

                $table->timestamps();
            });

            $this->loadRemoteModelData();
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

    /**
     * Recursively call the Remote Model API Endpoint to gather all data and save it.
     *
     * @param  int  $page
     * @return void
     * @throws \Exception
     */
    private function loadRemoteModelData(int $page = 1): void
    {
        $response = $this->callRemoteModelEndpoint($page);

        if (\array_key_exists('data', $response)) {
            // Paginated
            $this->insertRemoteModelData($response['data'], $response['per_page'] ?? 15);

            if (\array_key_exists('current_page', $response) && \array_key_exists('last_page', $response)) {
                // Call the next page from the paginated data.
                if ((int) $response['current_page'] < (int) $response['last_page']) {
                    $this->loadRemoteModelData((int) $response['current_page'] + 1);
                }
            }
        } else if (\count($response) > 0) {
            // Default to array of data.
            $this->insertRemoteModelData($response);
        }
    }

    public function callRemoteModelEndpoint(int $page = 1): array
    {
        $response = Http::timeout(10)->post($this->getEndpoint() . '?page=' . $page);

        if ($response->failed()) {
            throw new \Exception('Access to Remote Model `$endpoint` failed.');
        }

        return $response->json();
    }

    private function insertRemoteModelData(array $data, int $chunk = 15): void
    {
        $schema = $this->getSchema();
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
                                case \strtotime($value) !== false:
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
