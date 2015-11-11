<?php namespace Mreschke\Dbal\Providers;

use Config;
use Mreschke\Dbal\Mysql;
use Mreschke\Dbal\Mssql;
use Illuminate\Foundation\AliasLoader;
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
		// Register Facades
		$facade = AliasLoader::getInstance();
		$facade->alias('Mysql', 'Mreschke\Dbal\Facades\Mysql');
		$facade->alias('Mssql', 'Mreschke\Dbal\Facades\Mssql');

		// PHP Settings
		ini_set('mssql.timeout', '3600'); #default is 60 seconds, too short

		// Notice:  I don't believe these should ever be singletons.
		// If they are singletons, you may set a connection once, then call
		// another class that changes the connection, the previous class
		// will now have the new connection.  Will cause connection conflics.
		// Leave as ->bind()

		// Mysql Binding
		$this->app->bind('Mreschke\Dbal\Mysql', function() {
			return new Mysql(
				Config::get('database.connections'),
				'mysql'
			);
		});

		// Mssql Binding
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
