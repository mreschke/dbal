<?php namespace Mreschke\Dbal;

use PDO;

/**
 * MySQL database abstraction layer
 * @copyright 2014 Matthew Reschke
 * @license http://mreschke.com/license/mit
 * @author Matthew Reschke <mail@mreschke.com>
 * @depends php?-mysql
 */
class Mysql extends Dbal implements DbalInterface
{
    /**
     * Mssql escape function to prevent SQL injection attacks on input
     * @param  mixed $data
     * @return mixed escaped
     */
    public function escape($data)
    {
        return mysqli_real_escape_string($this->handle, $data);
    }

    /**
     * Build MSSQL specific Limit and Offset query
     * @param int $limit
     * @param int $offset
     * @param int $page
     * @return string
     */
    public function buildLimitOffset($limit, $offset, $page)
    {
        if (isset($limit) || isset($offset) || isset($page)) {
            $limit = $limit ?: 25;
            $offset = $offset ?: 0;
            if (isset($page)) $offset = ($this->page -1) * $limit;
            return " LIMIT $limit, $offset ";
        }
    }

}
