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
        if (! $this->endpoint) {
            $this->endpoint = '/' . Str::of($this::class)
                ->afterLast('\\')
                ->slug()
                ->value();
        }

        $domain = config('remote-models.domain', '');
        if (Str::endsWith($domain, '/')) {
            $domain = \rtrim($domain, '/');
        }

        $path = config('remote-models.api-path');
        if (Str::endsWith($path, '/') && Str::startsWith($this->endpoint, '/')) {
            $path = \rtrim($path, '/');
        }

        if (! Str::startsWith($path, '/')) {
            $path = '/' . $path;
        }

        return $domain . $path . $this->endpoint;
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
                $schema = [];

                // Load initial data from endpoint to gather columns.
                $response = $this->callRemoteModelEndpoint();

                if (\array_key_exists('data', $response)) {
                    // Paginated
                    if (\count($response['data']) > 0) {
                        $schema = \array_keys($response['data'][0]);
                    }
                } else if (\count($response) > 0) {
                    // Default to array of data.
                    $schema = \array_keys($response[0]);
                }

                if (\count($schema) === 0) {
                    throw new \Exception('No data returned from Remote Model `$endpoint`.');
                }

                $table->id();

                $schema = collect($schema)
                    ->filter(fn ($column) => ! \in_array($column, ['id', 'created_at', 'updated_at']))
                    ->toArray();

                foreach ($schema as $type => $column) {
                    if ($column === 'id' || $type === 'id') {
                        continue;
                    }

                    if (\gettype($type) === 'integer') {
                        // Default to string column.
                        $table->string($column)->nullable();
                    } else if (Str::endsWith($column, ['_at', '_on'])) {
                        $table->dateTime($column)->nullable();
                    } else {
                        $table->{$type}($column)->nullable();
                    }
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

    public function loadRemoteModelData(int $page = 1): void
    {
        $response = $this->callRemoteModelEndpoint($page);

        if (\array_key_exists('data', $response)) {
            // Paginated
            foreach (\array_chunk($response['data'], $response['per_page'] ?? 15) ?? [] as $inserts) {
                if (! empty($inserts)) {
                    static::insert($inserts);
                }
            }

            if (\array_key_exists('current_page', $response) && \array_key_exists('last_page', $response)) {
                if ((int) $response['current_page'] < (int) $response['last_page']) {
                    $this->loadRemoteModelData((int) $response['current_page'] + 1);
                }
            }
        } else if (\count($response) > 0) {
            // Default to array of data.
            foreach (\array_chunk($response, 15) ?? [] as $inserts) {
                if (! empty($inserts)) {
                    static::insert($inserts);
                }
            }
        }
    }

    public function callRemoteModelEndpoint(int $page = 1): array
    {
        $response = Http::timeout(10)->get($this->getEndpoint() . '?page=' . $page);

        if ($response->failed()) {
            throw new \Exception('Access to Remote Model `$endpoint` failed.');
        }

        return $response->json();
    }
}
