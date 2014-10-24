<?php namespace Mreschke\Dbal;

use Illuminate\Support\ServiceProvider;

/**
 * Provide Dbal services
 * @copyright 2014 Matthew Reschke
 * @license http://mreschke.com/license/mit
 * @author Matthew Reschke <mail@mreschke.com>
 */
class DbalServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = true;

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		\Lifecycle::add(__FILE__.' - '.__FUNCTION__, 1);

		// Bind Mysql to IoC
		$this->app->bind('Mreschke\Dbal\Mysql', function() {
			return new Mysql(
				\Config::get('my.db'),
				'mysql'
			);
		});

		// Bind Mssql to IoC
		$this->app->bind('Mreschke\Dbal\Mssql', function() {
			return new Mssql(
				\Config::get('my.db'),
				'mssql'
			);
		});
	}

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		\Lifecycle::add(__FILE__.' - '.__FUNCTION__);
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('Mreschke\Dbal\Mssql');
	}

}
