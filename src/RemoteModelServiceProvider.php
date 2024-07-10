<?php

namespace RemoteModels;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Class RemoteModelServiceProvider.
 */
class RemoteModelServiceProvider extends ServiceProvider
{
	/**
	 * Bootstrap the application services.
	 */
	public function boot(Router $router): void
	{
		if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/config/remote-models.php' => config_path('remote-models.php'),
            ], 'config');
		}

        $this->app->booted(function () use ($router) {
            if (count(config('remote-models.host_models', [])) > 0) {
                Route::group([
                    'namespace' => 'RemoteModels\Http\Controllers',
                ], fn () => $this->loadRoutesFrom(__DIR__ . '/Http/routes.php'));
            }
        });
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
