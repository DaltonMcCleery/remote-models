<?php

namespace RemoteModels;

use Illuminate\Foundation\Console\AboutCommand;
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

		AboutCommand::add('Remote Models', 'Version', '0.1.0');
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
