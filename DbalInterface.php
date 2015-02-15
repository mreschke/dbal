<?php namespace Mreschke\Dbal;

/**
 * Provides a contractual interface for Dbal implementations
 * @copyright 2014 Matthew Reschke
 * @license http://mreschke.com/license/mit
 * @author Matthew Reschke <mail@mreschke.com>
 */
interface DbalInterface
{

	/**
	 * Get this instance
	 * @return self
	 */
	public function getInstance();

	/** 
	 * Get the current connection name
	 */
	public function connectionName();

	/** 
	 * Get the current connection json string
	 */
	public function connectionString();	

	/**
	 * Change the connection string
	 * @param  string $connectionName new connection string name in $config array
	 * @return self chainable
	 */
	public function connection($connectionName);

	/**
	 * Disconnect and clear results and handles (optional)
	 */
	public function disconnect();

	/**
	 * Reset connection by clearing the handle
	 * @return void
	 */
	public function reset();

	/**
	 * Execute query using PHP mssql_query
	 * @param  string $query mssql compliant sql string
	 * @return self chainable
	 */
	public function execute($query = null);

	/**
	 * Alias to execute
	 * @param  string $query mssql compliant sql string
	 * @return self chainable
	 */
	public function query($query = null);

	/**
	 * Execute a stored procedure
	 * @param  string $procedure
	 * @param  array $params
	 * @return self chainable
	 */
	public function procedure($procedure, $params = null);

	/**
	 * Get entire data set as object
	 * @return object mssql_fetch_object
	 */
	public function get();

	/**
	 * Get entire data set as array. Optionally return only one column
	 * as array or or two columns for key/value associative array.
	 * @param  string $value optional value field
	 * @param  string $key optional key vield
	 * @param  boolean|string $addEmptyRow optional add empty item to array if $value or $key used, if string, use as empty key (default -1)
	 * @return object mssql_fetch_assoc
	 */
	public function getArray($key = null, $value = null, $addEmptyRow = false);

	/**
	 * Alias to getArray
	 * @param  string $value optional value field
	 * @param  string $key optional key vield
	 * @param  boolean|string $addEmptyRow optional add empty item to array if $value or $key used, if string, use as empty key (default -1)
	 * @return object mssql_fetch_assoc
	 */
	public function getAssoc($key = null, $value = null, $addEmptyRow = false);

	/**
	 * Alias to getArray but requires a value
	 * @param  string $value optional value field
	 * @param  string $key optional key vield
	 * @param  boolean|string $addEmptyRow optional add empty item to array if $value or $key used, if string, use as empty key (default -1)
	 * @return array
	 */
	public function lists($key, $value = null, $addEmptyRow = false);

	/**
	 * Get the first row in result
	 * @return object mssql_fetch_object
	 */
	public function first();

	/**
	 * Get the first row in result as array
	 * @return object mssql_fetch_assoc
	 */
	public function firstArray();

	/**
	 * Alias to firstArray
	 * @return object mssql_fetch_assoc
	 */
	public function firstAssoc();

	/**
	 * Pluck first row/colum or first row/specified column
	 * @param  string $column optional column to pluck
	 * @return mixed scalar
	 */
	public function pluck($column = null);

	/**
	 * Get count of rows in result set
	 * @return int
	 */
	public function count();

	/**
	 * Get count of columns in result set
	 * @return int
	 */
	public function fieldCount();

	/**
	 * Mssql escape function to prevent SQL injection attacks on input
	 * @param  mixed $data
	 * @return mixed escaped
	 */
	public function escape($data);

}
