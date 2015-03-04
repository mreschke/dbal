<?php namespace Mreschke\Dbal\Providers;

use Config;
use Mreschke\Dbal\Mysql;
use Mreschke\Dbal\Mssql;
use Illuminate\Foundation\AliasLoader;
use Mrcore\Modules\Foundation\Support\ServiceProvider;

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
