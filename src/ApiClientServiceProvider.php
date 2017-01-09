<?php

namespace Centralpos\ApiClient;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

class ApiClientServiceProvider extends ServiceProvider
{
	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * @return void
	 */
	public function boot(){
		
		$this->setupConfig();
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		//
		$this->app->singleton('apiclient', function(Container $app)
		{
			
			return new ApiClientManager($app['config'], $app['cache.store']);
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return [];
	}

	/**
	 * Setup the config.
	 *
	 * @return void
	 */
	protected function setupConfig()
	{
		$source = realpath(__DIR__.'/../config/api-client.php');

		$this->publishes([$source => config_path('api-client.php')]);

		$this->mergeConfigFrom($source, 'api-client');
	}
}
