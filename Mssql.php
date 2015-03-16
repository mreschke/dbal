<?php namespace Mreschke\Dbal;

use Illuminate\Support\Collection;

/**
 * Mssql database abstraction layer
 * @copyright 2014 Matthew Reschke
 * @license http://mreschke.com/license/mit
 * @author Matthew Reschke <mail@mreschke.com>
 * @depends php5-sybase
 */
class Mssql extends Builder implements DbalInterface
{
	private $connectionName;
	private $connectionString;
	private $config;
	private $handle;
	private $result;

	/**
	 * Create a new Mssql instance.
	 * $config[$connectionName] must be a json formatted string like:
	 * {"server":"yourserver","database":"yourdb","username":"youruser","password":"yourpass","type":"mssql"}.
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
	 * If facade, can also use Mssql::getFacadeRoot()
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
		if (isset($this->config[$connectionName])) {
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
		$handle = mssql_connect(
			$this->connectionString['host'],
			$this->connectionString['username'],
			$this->connectionString['password']
		) or die("Couldn't connect to SQL Server on ".$this->connectionString['host']);
		mssql_select_db(
			$this->connectionString['database'],
			$handle
		) or die("Couldn't open database ".$this->connectionString['database']);
		return $handle;
	}

	/**
	 * Disconnect and clear results and handles (optional)
	 * @return void
	 */
	public function disconnect()
	{
		mssql_free_result($this->result);
		mssql_close($this->handle);
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
			#mssql_query("SET ANSI_NULLS ON") or die("Error: ".mssql_get_last_message());    #Fixes a multi server UNION error
			#mssql_query("SET ANSI_WARNINGS ON") or die("Error: ".mssql_get_last_message()); #Fixes a multi server UNION error
			#$this->result = mssql_query($query) or die("Error: ".mssql_get_last_message());
			mssql_query("SET ANSI_NULLS ON");    #Fixes a multi server UNION error
			mssql_query("SET ANSI_WARNINGS ON"); #Fixes a multi server UNION error
			
			if (!$this->result = mssql_query($query)) {
				$error = "<div style='color: red;font-weight: bold'>".mssql_get_last_message()."</div>";
				#die($error);
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
		// Establish a connection for every query
		$this->handle = $this->connect();

		$statement = mssql_init($procedure) or die ("Failed to initialize procedure $procedure");
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
	}

    public function isGuid($guid)
    {
        if (!is_string($guid)) return false;
        if (strlen($guid)!=16) return false;
        $version=ord(substr($guid,7,1))>>4;
        // version 1 : Time-based version Uses timestamp, clock sequence, and MAC network card address
        // version 2 : Reserverd
        // version 3 : Name-based version Constructs values from a name for all sections
        // version 4 : Random version Use random numbers for all sections
        if ($version<1 || $version>4) return false;
        $typefield=ord(substr($guid,8,1))>>4;
        $type=-1;
        if (($typefield & bindec(1000))==bindec(0000)) $type=0; // type 0 indicated by 0??? Reserved for NCS (Network Computing System) backward compatibility
        if (($typefield & bindec(1100))==bindec(1000)) $type=2; // type 2 indicated by 10?? Standard format
        if (($typefield & bindec(1110))==bindec(1100)) $type=6; // type 6 indicated by 110? Reserved for Microsoft Corporation backward compatibility
        if (($typefield & bindec(1110))==bindec(1110)) $type=7; // type 7 indicated by 111? Reserved for future definition
        // assuming Standard type for SQL GUIDs
        if ($type!=2) return false;
        return true;
    }	

	/**
	 * Get entire data set as object
	 * @return object mssql_fetch_object
	 */
	public function get()
	{
		// Execute query if not already executed manually
		if (!isset($this->handle)) $this->execute();

		if ($this->count() > 0) {
			mssql_data_seek($this->result, 0);
			$rows = array();
			$firstRow = true;
			$guidColumns = null;
			$dateColumns = null;
			$checkColumns = null;
			while ($row = mssql_fetch_object($this->result)) {
				// Get column information
				if ($firstRow) {
					$firstRow = false;
					for ($f = 0; $f <= $this->fieldCount()-1; $f++) {
						// Column information
						$field = mssql_fetch_field($this->result, $f);
						$checkColumns[] = $field->name;
					}
				}

				// Loop each column and determine its data type.  If column row data is null
				// keep retrying on each row until data is found and the type is determined
				if (isset($checkColumns)) {
					foreach ($checkColumns as $colOffset => $name) {
						if (isset($row->$name)) {
							$field = mssql_fetch_field($this->result, $colOffset);
							$length = $field->max_length;
							$type = $field->type;
							
							// Type Detection
							if ($length == 16 && ($type == 'blob' || $type == 'unknown')) {
								// Column is a GUID
								$guidColumns[] = $name;
							
							} elseif ($type == 'datetime') {
								// Column is a datetime
								$dateColumns[] = $field->name;
							}

							// Remove from checkColumns
							unset($checkColumns[$colOffset]);

						}
					}
				}

				// Convert all GUID columns
				if (isset($guidColumns)) {
					foreach ($guidColumns as $guidColumn) {
						$row->$guidColumn = mssql_guid_string($row->$guidColumn);
					}
				}

				// Convert all DateTime Columns
				if (isset($dateColumns)) {
					foreach ($dateColumns as $dateColumn) {
						if (isset($row->$dateColumn)) {
							$row->$dateColumn = date("Y-m-d H:i:s", strtotime($row->$dateColumn));
						}
					}
				}

				// Add row to return
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
	 * @return object mssql_fetch_assoc|array
	 */
	public function getArray($value = null, $key = null, $addEmptyRow = false)
	{
		// Execute query if not already executed manually
		if (!isset($this->handle)) $this->execute();

		if ($this->count() > 0) {
			mssql_data_seek($this->result, 0);
			$rows = array();
			$firstRow = true;
			$guidColumns = null;
			$dateColumns = null;
			$checkColumns = null;

			// addEmptyRow can be either true/false in which case the blank key is -1
			// or addEmptyRow can be a string or '' which is used as the blank key itself
			// used for cases where we want a blank key which causes validation applications to
			// assume invalid
			if (is_bool($addEmptyRow) && $addEmptyRow == true) {
				$rows[-1] = '';
			} elseif (!is_bool($addEmptyRow) && isset($addEmptyRow)) {
				$rows[$addEmptyRow] = '';
			}

			while ($row = mssql_fetch_assoc($this->result)) {
				// Get column information
				if ($firstRow) {
					$firstRow = false;
					for ($f = 0; $f <= $this->fieldCount()-1; $f++) {
						// Column information
						$field = mssql_fetch_field($this->result, $f);
						$checkColumns[] = $field->name;
					}
				}

				// Loop each column and determine its data type.  If column row data is null
				// keep retrying on each row until data is found and the type is determined
				if (isset($checkColumns)) {
					foreach ($checkColumns as $colOffset => $name) {
						if (isset($row[$name])) {
							$field = mssql_fetch_field($this->result, $colOffset);
							$length = $field->max_length;
							$type = $field->type;
							
							// Type Detection
							if ($length == 16 && ($type == 'blob' || $type == 'unknown')) {
								// Column is a GUID
								$guidColumns[] = $name;
							
							} elseif ($type == 'datetime') {
								// Column is a datetime
								$dateColumns[] = $field->name;
							}

							// Remove from checkColumns
							unset($checkColumns[$colOffset]);

						}
					}
				}				

				// Convert all GUID columns
				if (isset($guidColumns)) {
					foreach ($guidColumns as $guidColumn) {
						$row[$guidColumn] = mssql_guid_string($row[$guidColumn]);
					}
				}

				// Convert all DateTime Columns
				if (isset($dateColumns)) {
					foreach ($dateColumns as $dateColumn) {
						if (isset($row[$dateColumn])) {
							$row[$dateColumn] = date("Y-m-d H:i:s", strtotime($row[$dateColumn]));
						}
					}
				}

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
	 * @return object mssql_fetch_assoc|array
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
	 * @return object mssql_fetch_object
	 */
	public function first()
	{
		// Execute query if not already executed manually
		if (!isset($this->handle)) $this->execute();

		if ($this->count() > 0) {
			mssql_data_seek($this->result, 0);
			$row = mssql_fetch_object($this->result);
			for ($f = 0; $f <= $this->fieldCount()-1; $f++) {
				$field = mssql_fetch_field($this->result, $f);
				$name = $field->name;
				$length = $field->max_length;
				$type = $field->type;
				if (isset($row->$name)) {
					if ($length == 16 && ($type == 'blob' || $type == 'unknown')) {
						// Column is a GUID
						$row->$name = mssql_guid_string($row->$name);

					} elseif ($type == 'datetime') {
						// Column is a datetime
						$row->$name = date("Y-m-d H:i:s", strtotime($row->$name));
					}
				}
			}
			return $row;
		}
	}

	/**
	 * Get the first row in result as array
	 * @return object mssql_fetch_assoc
	 */
	public function firstArray()
	{
		// Execute query if not already executed manually
		if (!isset($this->handle)) $this->execute();

		if ($this->count() > 0) {
			mssql_data_seek($this->result, 0);
			$row = mssql_fetch_assoc($this->result);	
			for ($f = 0; $f <= $this->fieldCount()-1; $f++) {
				$field = mssql_fetch_field($this->result, $f);
				$name = $field->name;
				$length = $field->max_length;
				$type = $field->type;
				if (isset($row[$name])) {
					if ($length == 16 && ($type == 'blob' || $type == 'unknown')) {
						// Column is a GUID
						$row[$name] = mssql_guid_string($row[$name]);

					} elseif ($type == 'datetime') {
						// Column is a datetime
						$row[$name] = date("Y-m-d H:i:s", strtotime($row[$name]));
					}
				}
			}
			return $row;
		}
	}

	/**
	 * Alias to firstArray
	 * @return object mssql_fetch_assoc
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
			mssql_data_seek($this->result, 0);
			if (isset($column)) {
				// Return by column name
				$row = mssql_fetch_assoc($this->result);
				$position = array_search($column, array_keys($row));
				$value = $row[$column];
			} else {
				// No column defined, return first columns data
				$row = mssql_fetch_row($this->result);
				$position = 0;
				$value = $row[0];
			}
			if (isset($value)) {
				$field = mssql_fetch_field($this->result, $position);
				$name = $field->name;
				$length = $field->max_length;
				$type = $field->type;			
				if ($length == 16 && ($type == 'blob' || $type == 'unknown')) {
					// Column is a GUID
					$value = mssql_guid_string($value);

				} elseif ($type == 'datetime') {
					// Column is a datetime
					$value = date("Y-m-d H:i:s", strtotime($value));
				}
			}
			return $value;
		}
	}

	/**
	 * Get count of rows in result set
	 * @return int
	 */
	public function count()
	{
		return mssql_num_rows($this->result);
	}

	/**
	 * Get count of columns in result set
	 * @return int
	 */
	public function fieldCount()
	{
		return mssql_num_fields($this->result);
	}

	/**
	 * Mssql escape function to prevent SQL injection attacks on input
	 * @param  mixed $data
	 * @return mixed escaped
	 */
	public function escape($data)
	{
		#http://stackoverflow.com/questions/574805/how-to-escape-strings-in-sql-server-using-php
		#This one removes or escapes all harmful characters
		if ( !isset($data) or empty($data) ) return '';
		if ( is_numeric($data) ) return $data;

		$non_displayables = array(
			'/%0[0-8bcef]/',            // url encoded 00-08, 11, 12, 14, 15
			'/%1[0-9a-f]/',             // url encoded 16-31
			'/[\x00-\x08]/',            // 00-08
			'/\x0b/',                   // 11
			'/\x0c/',                   // 12
			'/[\x0e-\x1f]/'             // 14-31
		);
		foreach ( $non_displayables as $regex )
			$data = preg_replace( $regex, '', $data );
		$data = str_replace("'", "''", $data );
		return $data;
	}

	/*
	public function outputCsv($filename, $save_path = null)
	{
		if (!$filename) $filename = 'results';
		mssql_data_seek($this->result, 0);
		if (isset($save_path)) {
			$tmp_dir = $save_path;
		} else {
			$tmp_dir = \Snippets\Config::WEB_TMP_DIR;	
		}
		if (!is_dir($tmp_dir)) exec('mkdir -p '.$tmp_dir);
		$filename = preg_replace("'\.csv'i", '', $filename)."_".date("Y-m-d-H-m-s").".csv";
		$fp = fopen($tmp_dir.$filename, 'w');

		// Write Headers
		$headers = array();
		for ($f = 0; $f <= $this->fieldCount-1; $f++) {
			$field = mssql_fetch_field($this->result, $f);
			$show = true;
			if (isset($this->columns)) {
				//Only show columns if in list of visible columns
				if (!in_array(strtolower($field->name), array_map('strtolower', $this->columns))) $show = false;
			}
			if ($show) $headers[] = $field->name;
		}
		fputcsv($fp, $headers);

		$data = array();
		for ($r = 0; $r <= $this->rowCount-1; $r++) {
			$row = mssql_fetch_row($this->result);
			for ($f = 0; $f <= $this->fieldCount-1; $f++) {
				$field = mssql_fetch_field($this->result, $f);
				//Field Types
				if ($field->type == 'unknown') {
					$row[$f] = mssql_guid_string($row[$f]);
				} elseif ($field->type == 'bit') {
					$row[$f] = ($row[$f] == 1 ? 'true' : 'false');
				}
				$output = $row[$f];
				$show = true;
				if (isset($this->columns)) {
					//Only show columns if in list of visible columns
					if (!in_array(strtolower($field->name), array_map('strtolower', $this->columns))) $show = false;
				}
				foreach($this->columns_output as $key=>$value) {
					if (strtolower($key) == strtolower($field->name)) {
						$output = preg_replace('"%data%"', $output, $value);
						break;
					}
				}
				if ($show) $data[] = $output;
			}
			fputcsv($fp, $data);
			$data = array();
		}
		fclose($fp);

		if (!isset($save_path)) {
			//Download File
			echo "<META HTTP-EQUIV='Refresh' Content='0; URL=".\Snippets\Config::WEB_TMP_URL.$filename."'>";
		}
	}
	*/

}
