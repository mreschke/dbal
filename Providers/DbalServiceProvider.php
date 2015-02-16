<?php namespace Mreschke\Dbal\Providers;

use Config;
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
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		//
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		// Register Dbal Facades
		$loader = \Illuminate\Foundation\AliasLoader::getInstance();
		$loader->alias('Mysql', 'Mreschke\Dbal\Facades\Mysql');
		$loader->alias('Mssql', 'Mreschke\Dbal\Facades\Mssql');

		// Bind Mysql to IoC
		$this->app->bind('Mreschke\Dbal\Mysql', function() {
			return new Mysql(
				Config::get('database.connections'),
				'mysql'
			);
		});

		// Bind Mssql to IoC
		$this->app->bind('Mreschke\Dbal\Mssql', function() {
			return new Mssql(
				Config::get('database.connections'),
				'sqlsrv'
			);
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array(
			'Mreschke\Dbal\Mssql',
			'Mreschke\Dbal\Mysql',
		);
	}

}
