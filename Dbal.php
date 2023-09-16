<?php namespace Mreschke\Dbal;

use PDO;
use Exception;
use PDOException;
use Illuminate\Support\Collection;

/**
 * Mssql database abstraction layer
 * @copyright 2014 Matthew Reschke
 * @license http://mreschke.com/license/mit
 * @author Matthew Reschke <mail@mreschke.com>
 * @depends php?-sybase
 */
class Dbal extends Builder
{
    protected $connectionName;
    protected $connectionString;
    protected $connectionType;
    protected $config;
    protected $handle;
    protected $result;

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
     * @return PDO
     */
    protected function connect()
    {
        // Autodetect connection type (mysql, mssql...) based on parent class
        $class = get_class($this);
        if (preg_match('/mssql/i', $class)) {
            $lib = "dblib";
            $defaultPort = 1433;
            $this->connectionString['type'] = 'mssql';
            $options = []; //dblib does NOT support any options
        } elseif (preg_match('/mysql/i', $class)) {
            $lib = 'mysql';
            $defaultPort = 3306;
            $this->connectionString['type'] = 'mysql';
            $options = isset($this->connectionString['options']) ? $this->connectionString['options'] : [];
            // $options = [
            //     PDO::ATTR_EMULATE_PREPARES => false,
            //     PDO::ATTR_STRINGIFY_FETCHES => false,
            //     #PDO::ATTR_CASE => PDO::CASE_NATURAL,
            //     #PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            //     #PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
            // ];
            //FIXME, merge with config/databse.php options
        } else {
            throw new Exception("Connecton type not supported");
        }

        $host = $this->connectionString['host'];
        $port = isset($this->connectionString['port']) ? $this->connectionString['port'] : $defaultPort;
        $database = $this->connectionString['database'];
        $username = $this->connectionString['username'];
        $password = $this->connectionString['password'];

        try {
            $handle = new PDO(
                "$lib:host=$host:$port;dbname=$database",
                $username,
                $password,
                $options
            );
        } catch (PDOException $e) {
            throw new PDOException($e);
        }
        return $handle;
    }

    /**
     * Disconnect and clear results and handles (optional)
     * @return void
     */
    public function disconnect()
    {
        $this->handle = null;
        $this->result = null;
    }

    /**
     * Alias to disconnect
     * @return void
     */
    public function reset()
    {
        $this->disconnect();
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

            if ($this->connectionString['type'] == 'mssql') {
                // This allows Mssql linked server queries
                $set = 'SET ANSI_NULLS, QUOTED_IDENTIFIER, CONCAT_NULL_YIELDS_NULL, ANSI_WARNINGS, ANSI_PADDING ON';
                $this->result = $this->handle->prepare($set);
                $this->result->execute();
            }
            $this->result = $this->handle->prepare($query);
            $this->result->execute();
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

        if (!isset($params)) {
            $this->result = $this->handle->prepare("exec $procedure");
        } else {
            // Add each param to @param query string
            $query = "exec $procedure ";
            foreach ($params as $param) {
                $query .= "@".$param['name']."=?, ";
            }
            $query = substr($query, 0, -2);
            $this->result = $this->handle->prepare($query);

            // Bind each param value
            $i = 0;
            foreach ($params as $param) {
                $this->result->bindParam($i+1, $params[$i]['value']);
                $i++;
            }
        }
        $this->result->execute();
        return $this;
    }

    /**
     * Fetch records as array of objects or array of arrays
     * @return array
     */
    protected function fetch($mode = 'object', $first = false)
    {
        // Execute query if not already executed manually
        if (!isset($this->handle)) {
            $this->execute();
        }

        if ($mode == 'object') {
            $isObject = true;
            $this->result->setFetchMode(PDO::FETCH_OBJ);
        } else {
            $isObject = false;
            $this->result->setFetchMode(PDO::FETCH_ASSOC);
        }

        $rows = array();
        while ($row = $this->result->fetch()) {
            $rows[] = $row;
            if ($first) {
                return $row;
            }
        }
        return $rows;
    }

    /**
     * Get entire data set as object
     * @return \Illuminate\Support\Collection|null
     */
    public function get()
    {
        $results = $this->fetch('object');
        return empty($results) ? null : collect($results);
    }

    /**
     * Alias to get
     * @return \Illuminate\Support\Collection|array
     */
    public function all()
    {
        return $this->get();
    }

    /**
     * Get entire data set as array. Optionally return only one column
     * as array or or two columns for key/value associative array.
     * @param  string $value optional value field
     * @param  string $key optional key vield
     * @param  boolean|string $addEmptyRow optional add empty item to array if $value or $key used, if string, use as empty key (default 0)
     * @return \Illuminate\Support\Collection|array|null
     */
    public function getArray($value = null, $key = null, $addEmptyRow = false)
    {
        $results = $this->fetch('array');
        if (empty($results)) {
            return null;
        }

        $results = collect($results);

        if (isset($value) && (isset($key))) {
            $results = $results->pluck($value, $key);
            if ($addEmptyRow) {
                $results->prepend('', !is_bool($addEmptyRow) ? $addEmptyRow : -1);
            }
        } elseif (isset($value)) {
            $results = $results->pluck($value);
            if ($addEmptyRow) {
                $results->prepend('');
            }
        }
        return isset($value) ? $results->toArray() : $results;
    }

    /**
     * Alias to getArray
     * @param  string $value optional value field
     * @param  string $key optional key vield
     * @param  boolean|string $addEmptyRow optional add empty item to array if $value or $key used, if string, use as empty key (default 0)
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
     * @param  boolean|string $addEmptyRow optional add empty item to array if $value or $key used, if string, use as empty key (default 0)
     * @return array
     */
    public function pluck($value, $key = null, $addEmptyRow = false)
    {
        return $this->getArray($value, $key, $addEmptyRow);
    }

    /**
     * Alias to pluck
     */
    public function lists_DEPRECATED($value, $key = null, $addEmptyRow = false)
    {
        // Laravel deprecated theirs, they use pluck now.
        return $this->pluck($value, $key, $addEmptyRow);
    }

    /**
     * Get the first row in result as object
     * @return object|null
     */
    public function first()
    {
        $results = $this->fetch('object', true);
        return empty($results) ? null : $results;
    }

    /**
     * Get the first row in result as array
     * @return array
     */
    public function firstArray()
    {
        $results = $this->fetch('array', true);
        return empty($results) ? null : $results;
    }

    /**
     * Alias to firstArray
     * @return array
     */
    public function firstAssoc()
    {
        return $this->firstArray();
    }

    /**
     * Pluck first row/colum or first row/specified column
     * @param  string $column optional column to pluck
     * @return mixed scalar|null
     */
    public function value($column = null)
    {
        $result = $this->firstAssoc('object', true);
        if (!isset($result)) {
            return null;
        }

        if (isset($column)) {
            if (isset($result[$column])) {
                return $result[$column];
            }
            return null;
        } else {
            return head($result);
        }
    }

    /**
     * Get count of rows in result set
     * @return int
     */
    public function count()
    {
        $this->result->setFetchMode(PDO::FETCH_ASSOC);
        return cnt($this->result->fetchAll());
    }

    /**
     * Get count of columns in result set
     * @return int
     */
    public function fieldCount()
    {
        return $this->result->columnCount();
    }

    /**
     * Mssql escape function to prevent SQL injection attacks on input
     * @param  mixed $data
     * @return mixed escaped
     */
    public function escape($data)
    {
        // PDO quote in PHP7 was segfault...try later
        #if (!isset($this->handle)) $this->handle = $this->connect();
        #return $this->handle->quote($data);

        // FIXME: each class has its own override for now until PDO quote works
    }

    /**
     * Get this instance
     * @return $this
     */
    public function getInstance()
    {
        return $this;
    }

    /**
     * Get the current connection name
     * @return string
     */
    public function connectionName()
    {
        return $this->connectionName;
    }

    /**
     * Get the current connection string array
     * @return array
     */
    public function connectionString()
    {
        return $this->connectionString;
    }
}
