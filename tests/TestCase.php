<?php

namespace Tests;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use RemoteModels\RemoteModelsServiceProvider;
use Tests\Models\Celebrity;

abstract class TestCase extends OrchestraTestCase
{
    public string $cachePath;

	protected function getEnvironmentSetUp($app): void
	{
		$app['config']->set('database.default', 'testbench');
		$app['config']->set('database.connections.testbench', [
			'driver'   => 'sqlite',
			'database' => ':memory:',
			'prefix'   => '',
		]);

        Config::set('remote-models.domain', 'https://yourdomain.com/');
        Config::set('remote-models.host_models', [
            Celebrity::class,
        ]);
	}

	protected function defineDatabaseMigrations(): void
	{
		$this->loadLaravelMigrations(['--database' => 'testbench']);
	}

	protected function setUp(): void
	{
		parent::setUp();

        config(['remote-models.cache-path' => $this->cachePath = __DIR__ . '/cache']);

        if (! file_exists($this->cachePath)) {
            mkdir($this->cachePath, 0777, true);
        }

        File::cleanDirectory($this->cachePath);
	}

    protected function tearDown(): void
    {
        File::cleanDirectory($this->cachePath);

        parent::tearDown();
    }

	protected function getPackageProviders($app): array
	{
		return [
			RemoteModelsServiceProvider::class,
		];
	}
}
