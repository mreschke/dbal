<?php namespace Mreschke\Dbal;

/**
 * Dbal query builder
 * @copyright 2014 Matthew Reschke
 * @license http://mreschke.com/license/mit
 * @author Matthew Reschke <mail@mreschke.com>
 */
abstract class Builder implements BuilderInterface
{
    /**
     * Tables primary key column
     * @var string|array
     */
    protected $key = 'id';

    /**
     * The select columns
     * @var array
     */
    protected $select;

    /**
     * The count query
     * @var array
     */
    protected $selectCount;

    /**
     * Set distinct
     * @var array
     */
    protected $distinct;

    /**
     * The tables to query
     * @var string
     */
    protected $from;

    /**
     * The where statement
     * @var string
     */
    protected $where;

    /**
     * The group by statement
     * @var string
     */
    protected $groupBy;

    /**
     * The having statement
     * @var string
     */
    protected $having;

    /**
     * The order by statement
     * @var string
     */
    protected $orderBy;

    /**
     * The limit by statement
     * @var string
     */
    protected $limit;

    /**
     * The offset by statement
     * @var string
     */
    protected $offset;

    /**
     * The page by statement for limit and offset
     * @var string
     */
    protected $page;

    /**
     * All of the available clause operators
     * @var array
     */
    protected $operators = array(
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'like', 'not like', 'between', 'ilike',
        '&', '|', '^', '<<', '>>',
    );

    /**
     * Configure the Dbal query builder
     * @return void
     */
    public function configureBuilder()
    {
        $this->key = null;
        $this->select = ['*'];
        $this->selectCount = null;
        $this->distinct = false;
        $this->from = null;
        $this->where = null;
        $this->groupBy = null;
        $this->having = null;
        $this->orderBy = null;
        $this->limit = null;
        $this->offset = null;
        $this->page = null;
    }

    /**
     * Return the dbal database instance
     * @return DbalInterface
     */
    public function dbInstance()
    {
        return $this;
    }

    /**
     * Build and return the query
     * @return string
     */
    public function queryBuilder()
    {
        $query = "SELECT ";
        if ($this->distinct and !isset($this->selectCount)) {
            $query .= "DISTINCT ";
        }

        if (isset($this->select) && !isset($this->selectCount)) {
            foreach ($this->select as $column) {
                $query .= "$column, ";
            }
            $query = substr($query, 0, -2);
        }

        if (isset($this->selectCount)) {
            if ($this->distinct) {
                $query .= "count(DISTINCT $this->selectCount) ";
            } else {
                $query .= "count($this->selectCount) ";
            }
        }

        $query .= " FROM $this->from ";

        if (isset($this->where)) {
            $query .= "WHERE $this->where ";
        }

        if (isset($this->groupBy)) {
            $query .= "GROUP BY $this->groupBy ";
        }
        if (isset($this->having)) {
            $query .= "HAVING $this->having ";
        }
        if (isset($this->orderBy) && !isset($this->selectCount)) {
            $query .= "ORDER BY $this->orderBy ";
        }

        // Add in limit, offset and paging per database type
        if (!isset($this->selectCount)) {
            $query .= $this->dbInstance()->buildLimitOffset($this->limit, $this->offset, $this->page);
        }

        return $query;
    }

    /**
     * Alias to queryBuilder
     * @return string
     */
    public function toSql()
    {
        return $this->queryBuilder();
    }

    /**
     * Set a new select statement
     * @param  string $sql select statement
     * @return self chainable
     */
    public function select($columns = array('*'))
    {
        $this->select = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    public function selectCount($sql) {
        $this->selectCount = $sql;
        return $this;
    }

    /**
     * Add a new column to the select query
     * @param string $column
     */
    public function addSelect($column)
    {
        $column = is_array($column) ? $column : func_get_args();
        $this->select = array_merge((array) $this->select, $column);
        return $this;
    }

    /**
     * Set a distinct flag
     * @param  boolean $distinct = true
     * @return self chainable
     */
    public function distinct($distinct = true)
    {
        $this->distinct = $distinct;
        return $this;
    }

    /**
     * Set a new limit statement for limit and offset
     * @param int $page
     * @return self chainable
     */
    public function limit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Set a new offset statement for limit and offset
     * @param int $page
     * @return self chainable
     */
    public function offset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Set a new page statement for limit and offset
     * @param int $page
     * @return self chainable
     */
    public function page($page)
    {
        $this->page = $page;
        return $this;
    }

    /**
     * Set a new from statement
     * @param  string $sql from statement
     * @return self chainable
     */
    public function from($sql)
    {
        // Reset connection for next query
        $this->dbInstance()->reset();

        $this->from = $sql;
        return $this;
    }

    /**
     * Alias to from
     * @param  string $sql from statement
     * @return self chainable
     */
    public function table($sql)
    {
        $this->from($sql);
        return $this;
    }

    /**
     * Set a new primary key
     * @param  mixed $sql from statement
     * @return self chainable
     */
    public function key($sql)
    {
        $this->key = $sql;
        return $this;
    }

    /**
     * Add a where clause to the query
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @return self chainable
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (func_num_args() == 1) {
            // Raw where query
            $sql = $column;
        } else {
            if (func_num_args() == 2) {
                list($value, $operator) = array($operator, '=');
            }

            if (!in_array(strtolower($operator), $this->operators)) {
                $operator = "=";
            }

            if (is_bool($value)) {
                $value = $value ? 1 : 0;
            } else {
                $value = $this->dbInstance()->escape($value);
                if (!is_numeric($value)) {
                    $value = "'".$value."'";
                }
            }

            $sql = "$column $operator $value";
        }

        if (isset($this->where)) {
            $sql = "$this->where $boolean $sql";
        }
        $this->where = $sql;
        return $this;
    }

    /**
     * Add an or where clause to the query
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @return selc chainable
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Add a new group by statement
     * @param  string $sql group by statement
     * @return self chainable
     */
    public function groupBy($sql)
    {
        if ($sql == '') {
            $sql = null;
        }
        $this->groupBy = $sql;
        return $this;
    }

    /**
     * Set a new having statement
     * @param  string $sql having statement
     * @return self chainable
     */
    public function having($sql)
    {
        if ($sql == '') {
            $sql = null;
        }
        $this->having = $sql;
        return $this;
    }

    /**
     * Set a new order by statement
     * @param  string $sql order bystatement
     * @return self chainable
     */
    public function orderBy($sql)
    {
        if ($sql == '') {
            $sql = null;
        }
        $this->orderBy = $sql;
        return $this;
    }

    /**
     * Alias to execute
     * @return dbal resource
     */
    public function query()
    {
        return $this->execute();
    }

    /**
     * Execute query builder
     * @param  string $query option if not set use query builder
     * @return dbal resource
     */
    public function execute($query = null)
    {
        if (!isset($query)) {
            $query = $this->queryBuilder();
        }
        $this->configureBuilder();
        return $this->dbInstance()->execute($query);
    }

    /**
     * Alias to execute
     * @return dbal resource
     */
    public function all()
    {
        return $this->execute();
    }

    /**
     * Return one row by one or more primary keys
     * @param  mixed $id
     * @return dbal resource
     */
    public function find($id)
    {
        // $id and $keys can be a string, or an array, or multipel parameters
        // There should be as many $ids as there are keys
        // convert $id and $keys into an array
        $ids = is_array($id) ? $id : func_get_args();
        $keys = $this->key;
        if (!is_array($keys)) {
            $keys = explode(',', $keys);
        }

        if (cnt($ids) == cnt($keys)) {
            for ($i = 0; $i <= cnt($keys)-1; $i++) {
                $this->where(trim($keys[$i]), trim($ids[$i]));
            }
            $this->orderBy(null);
            return $this->execute()->first();
        } else {
            throw new \Exception('incorrect number of primary key values');
        }
    }

    /**
     * Return one row as array by one or more primary keys
     * @param  mixed $id
     * @return dbal resource
     */
    public function findArray($id)
    {
        // $id and $keys can be a string, or an array, or multipel parameters
        // There should be as many $ids as there are keys
        // convert $id and $keys into an array
        $ids = is_array($id) ? $id : func_get_args();
        $keys = $this->key;
        if (!is_array($keys)) {
            $keys = explode(',', $keys);
        }

        if (cnt($ids) == cnt($keys)) {
            for ($i = 0; $i <= cnt($keys)-1; $i++) {
                $this->where(trim($keys[$i]), trim($ids[$i]));
            }
            $this->orderBy(null);
            return $this->execute()->firstArray();
        } else {
            throw new \Exception('incorrect number of primary key values');
        }
    }

    /**
     * Insert new record
     * @param  object $record
     * @return execute results
     */
    public function insert($record)
    {
        $query = "INSERT INTO $this->from (";
        foreach ($record as $key => $value) {
            $query .= "$key, ";
        }
        $query = substr($query, 0, -2).') VALUES (';

        foreach ($record as $key => $value) {
            if (!isset($value)) {
                $query .= "null, ";
            } elseif (is_bool($value)) {
                $query .= ($value ? 1 : 0).', ';
            } elseif (is_numeric($value)) {
                // No escape() in numeric, or 0 are turned to ''
                $query .= "$value, ";
            } elseif (preg_match('"^\\\\(.*)"', $value, $matches)) {
                $query .= $matches[1].', ';
            } else {
                $query .= "'".$this->dbInstance()->escape($value)."', ";
            }
        }
        $query = substr($query, 0, -2).')';
        $query .= " SELECT @@IDENTITY";
        return $this->execute($query);
    }

    /**
     * Update one record by one or more primary keys
     * @param  object $record
     * @return execute results
     */
    public function update($record)
    {
        $keys = $this->key;
        if (!is_array($keys)) {
            $keys = explode(',', $keys);
        }

        $originalPKValues = array();
        foreach ($keys as $key) {
            $key = trim($key);
            $originalPKValues[] = $record->$key;
        }

        // Get the original record by primary key(s)
        $original = $this->find($originalPKValues);

        $foundValue = false;
        $query = "UPDATE $this->from SET ";
        foreach ($record as $key => $value) {
            if ($value != $original->$key) {
                if (!isset($value)) {
                    $query .= "$key = null, ";
                } elseif (is_bool($value)) {
                    $query .= "$key = ".($value ? 1 : 0).', ';
                } elseif (is_numeric($value)) {
                    // No escape() in numeric, or 0 are turned to ''
                    $query .= "$key = $value, ";
                } elseif (preg_match('"^\\\\(.*)"', $value, $matches)) {
                    $query .= "$key = ".$matches[1].', ';
                } else {
                    $query .= "$key = '".$this->dbInstance()->escape($value)."', ";
                }
                $foundValue = true;
            }
        }
        if ($foundValue) {
            $query = substr($query, 0, -2)." WHERE ";
            for ($i = 0; $i <= cnt($keys)-1; $i++) {
                $query .= $keys[$i]." = '".$this->dbInstance()->escape(trim($originalPKValues[$i]))."' AND ";
            }
            $query = substr($query, 0, -5);
            return $this->execute($query);
        }
    }

    /**
     * Delete one record by one or more primary keys
     * @param  mixed $id
     * @return execute results
     */
    public function delete($id)
    {
        $ids = is_array($id) ? $id : func_get_args();
        $keys = $this->key;
        if (!is_array($keys)) {
            $keys = explode(',', $keys);
        }

        $query = "DELETE FROM $this->from WHERE ";
        for ($i = 0; $i <= cnt($keys)-1; $i++) {
            $query .= $keys[$i]." = '".$this->dbInstance()->escape(trim($ids[$i]))."' AND ";
        }
        $query = substr($query, 0, -5);
        return $this->execute($query);
    }
}
