<?php namespace Mreschke\Dbal\Facades;

/**
 * Provides the facade for Mreschke\Dbal\Mysql.
 * @copyright 2014 Matthew Reschke
 * @license http://mreschke.com/license/mit
 * @author Matthew Reschke <mail@mreschke.com>
 */
class Mysql extends \Illuminate\Support\Facades\Facade {

	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor() { return 'Mreschke\Dbal\Mysql'; }

}
