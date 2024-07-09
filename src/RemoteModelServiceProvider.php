<?php

namespace RemoteModels;

use Illuminate\Support\ServiceProvider;

/**
 * Class RemoteModelServiceProvider.
 */
class RemoteModelServiceProvider extends ServiceProvider
{
	/**
	 * Bootstrap the application services.
	 */
	public function boot(): void
	{
		if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/config/remote-models.php' => config_path('remote-models.php'),
            ], 'config');
		}
	}

	/**
	 * Register the application services.
	 */
	public function register(): void
	{
        $this->mergeConfigFrom(
            __DIR__.'/config/remote-models.php',
            'remote-models'
        );
	}
}
