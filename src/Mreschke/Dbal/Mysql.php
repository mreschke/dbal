<?php namespace Mreschke\Dbal;

use Illuminate\Support\Collection;

/**
 * Mysql database abstraction layer
 * @copyright 2014 Matthew Reschke
 * @license http://mreschke.com/license/mit
 * @author Matthew Reschke <mail@mreschke.com>
 * @depends php5-mysql
 */
class Mysql extends Builder implements DbalInterface
{
	private $connectionName;
	private $connectionString;
	private $config;
	private $handle;
	private $result;

	/**
	 * Create a new Mssql instance.
	 * $config[$connectionName] must be a json formatted string like:
	 * {"server":"yourserver","port":"3306":database":"yourdb","username":"youruser","password":"yourpass","type":"mssql"}.
	 * Optional: port
	 * @param array $config associative array with $connectionNname as key
	 * @param string $connectionName
	 */
	public function __construct($config, $connectionName)
	{
		$this->config = $config;
		$this->connection($connectionName);
		$this->configureBuilder();
	}

	/**
	 * Get this instance
	 * @return self
	 */
	public function getInstance()
	{
		return $this;
	}

	/** 
	 * Get the current connection name
	 */
	public function connectionName()
	{
		return $this->connectionName;
	}

	/** 
	 * Get the current connection json string
	 */
	public function connectionString()
	{
		return $this->connectionString;
	}

	/**
	 * Change the connection string
	 * @param  string $connectionName new connection string name in $config array
	 * @return self chainable
	 */
	public function connection($connectionName)
	{
		if (str_contains($connectionName, '{')) {
			// Using an actual json string
			$this->connectionString = $connectionName;
			$connectionName = 'custom';
		} else {
			// Using a config key that points to a json string
			$this->connectionString = $this->config[$connectionName];
		}
		$this->connectionName = $connectionName;
		return $this;
	}

	/**
	 * Connect to the database
	 * @return void
	 */
	private function connect() {
		$connectionString = json_decode($this->connectionString);
		$port = 3306;
		if (isset($connectionString->port)) $port = $connectionString->port;
		$handle = new \mysqli(
			$connectionString->server,
			$connectionString->username,
			$connectionString->password,
			$connectionString->database,
			$port
		);
		if (mysqli_connect_error()) {
			die ("Couldn't connect to MySQL Server on ".$connectionString->server.".<br />Error: ".mysqli_connect_error());
		}
		return $handle;
	}

	/**
	 * Disconnect and clear results and handles (optional)
	 * @return void
	 */
	public function disconnect()
	{
		$this->handle->close();
	}

	/**
	 * Reset connection by clearing the handle
	 * @return void
	 */
	public function reset()
	{
		$this->handle = null;
		$this->result = null;
	}

	/**
	 * Execute query using PHP mssql_query
	 * @param  string $query mssql compliant sql string
	 * @return self chainable
	 */
	public function execute($query = null)
	{

		if (!isset($query)) {
			$query = $this->queryBuilder();
		}

		// Reset builder
		$this->configureBuilder();
		$this->reset();

		if (isset($query)) {
			// Establish a connection for every query
			$this->handle = $this->connect();

			//Execute SQL Query
			#$this->result = $this->handle->query($query);
			if (!$this->result = $this->handle->query($query)) {
				$error = "<div style='color: red;font-weight: bold'>".mssql_get_last_message()."</div>";
				echo $error;
			}
		}
		return $this;
	}

	/**
	 * Alias to execute
	 * @param  string $query mssql compliant sql string
	 * @return self chainable
	 */
	public function query($query = null)
	{
		return $this->execute($query);
	}

	/**
	 * Execute a stored procedure
	 * @param  string $procedure
	 * @param  array $params
	 * @return self chainable
	 */
	public function procedure($procedure, $params = null)
	{
		/*$statement = mssql_init($procedure) or die ("Failed to initialize procedure $procedure");
		if (isset($params)) {
			foreach ($params as $param) {
				mssql_bind($statement, '@'.$param['name'], $param['value'], $param['type']);
			}
		}
		if (!$this->result = mssql_execute($statement)) {
			#print "Could not execute stored procedure, invalid paramaters?";
			$error = "<div style='color: red;font-weight: bold'>".mssql_get_last_message()."</div>";
			#die($error);
			echo $error;
		}
		return $this;
		*/
	}

	/**
	 * Get entire data set as object
	 * @return object mysqli_fetch_object
	 */
	public function get()
	{
		// Execute query if not already executed manually
		if (!isset($this->handle)) $this->execute();

		if ($this->count() > 0) {
			mysqli_data_seek($this->result, 0);
			$rows = array();
			while ($row = mysqli_fetch_object($this->result)) {
				$rows[] = $row;
			}
			// Return is a Laravel Illuminate Collection
			return new Collection($rows);
		}
	}

	/**
	 * Get entire data set as array. Optionally return only one column
	 * as array or or two columns for key/value associative array.
	 * @param  string $value optional value field
	 * @param  string $key optional key vield
	 * @param  boolean|string $addEmptyRow optional add empty item to array if $value or $key used, if string, use as empty key (default -1)
	 * @return object mysqli_fetch_assoc|array
	 */
	public function getArray($value = null, $key = null, $addEmptyRow = false)
	{
		// Execute query if not already executed manually
		if (!isset($this->handle)) $this->execute();

		if ($this->count() > 0) {
			mysqli_data_seek($this->result, 0);
			$rows = array();

			// addEmptyRow can be either true/false in which case the blank key is -1
			// or addEmptyRow can be a string or '' which is used as the blank key itself
			// used for cases where we want a blank key which causes validation applications to
			// assume invalid
			if (is_bool($addEmptyRow) && $addEmptyRow == true) {
				$rows[-1] = '';
			} elseif (!is_bool($addEmptyRow) && isset($addEmptyRow)) {
				$rows[$addEmptyRow] = '';
			}

			while ($row = mysqli_fetch_assoc($this->result)) {
				if (isset($key) && isset($value)) {
					// Return only a key/value assoc array
					$rows[$row[$key]] = $row[$value];
				} elseif (isset($value)) {
					// Return just an array or value column
					$rows[] = $row[$value];
				} else {
					// Return full assoc array of all fields
					$rows[] = $row;
				}
			}
			if (isset($value)) {
				// Return array
				return $rows;
			} else {
				// Return is a Laravel Illuminate Collection
				return new Collection($rows);
			}
		}
	}

	/**
	 * Alias to getArray
	 * @param  string $value optional value field
	 * @param  string $key optional key vield
	 * @param  boolean|string $addEmptyRow optional add empty item to array if $value or $key used, if string, use as empty key (default -1)
	 * @return object mysqli_fetch_assoc|array
	 */
	public function getAssoc($value = null, $key = null, $addEmptyRow = false)
	{
		return $this->getArray($value, $key, $addEmptyRow);
	}
	
	/**
	 * Alias to getArray but requires a value
	 * @param  string $value optional value field
	 * @param  string $key optional key vield
	 * @param  boolean|string $addEmptyRow optional add empty item to array if $value or $key used, if string, use as empty key (default -1)
	 * @return array
	 */
	public function lists($value, $key = null, $addEmptyRow = false)
	{
		return $this->getAssoc($value, $key, $addEmptyRow);
	}

	/**
	 * Get the first row in result as object
	 * @return object mysqli_fetch_object
	 */
	public function first()
	{
		// Execute query if not already executed manually
		if (!isset($this->handle)) $this->execute();

		if ($this->count() > 0) {
			mysqli_data_seek($this->result, 0);
			return mysqli_fetch_object($this->result);
		}
	}

	/**
	 * Get the first row in result as array
	 * @return object mysqli_fetch_assoc
	 */
	public function firstArray()
	{
		// Execute query if not already executed manually
		if (!isset($this->handle)) $this->execute();

		if ($this->count() > 0) {
			mysqli_data_seek($this->result, 0);
			return mysqli_fetch_assoc($this->result);	
		}
	}

	/**
	 * Alias to firstArray
	 * @return object mysqli_fetch_assoc
	 */
	public function firstAssoc()
	{
		return $this->firstArray();
	}
	
	/**
	 * Pluck first row/colum or first row/specified column
	 * @param  string $column optional column to pluck
	 * @return mixed scalar
	 */
	public function pluck($column = null)
	{
		// Execute query if not already executed manually
		if (!isset($this->handle)) $this->execute();

		if ($this->count() > 0) {
			mysqli_data_seek($this->result, 0);
			if (isset($column)) {
				// Return by column name
				$row = mysqli_fetch_assoc($this->result);
				return $row[$column];
			} else {
				// No column defined, return first columns data
				$row = mysqli_fetch_row($this->result);
				return $row[0];
			}
		}
	}

	/**
	 * Get count of rows in result set
	 * @return int
	 */
	public function count()
	{
		return $this->result->num_rows;
	}

	/**
	 * Get count of columns in result set
	 * @return int
	 */
	public function fieldCount()
	{
		return $this->result->field_count;
	}

	/**
	 * Mssql escape function to prevent SQL injection attacks on input
	 * @param  mixed $data
	 * @return mixed escaped
	 */
	public function escape($data)
	{
		return mysqli_real_escape_string($this->handle, $data);
	}

}
