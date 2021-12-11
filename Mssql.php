<?php namespace Mreschke\Dbal;

use PDO;

/**
 * Mssql database abstraction layer
 * @copyright 2014 Matthew Reschke
 * @license http://mreschke.com/license/mit
 * @author Matthew Reschke <mail@mreschke.com>
 * @depends php?-sybase
 */
class Mssql extends Dbal implements DbalInterface
{

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
            if (isset($page)) $offset = ($page -1) * $limit;

            return " OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY ";
            // 1-10
            // SELECT * FROM SSO.dbo.tbl_user ORDER BY user_id OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY;
            // 11-20
            // SELECT * FROM SSO.dbo.tbl_user ORDER BY user_id OFFSET 10 ROWS FETCH NEXT 10 ROWS ONLY;
        }
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
        $firstRow = true;
        $columnTypes = [];
        while ($row = $this->result->fetch()) {
            if ($firstRow) {
                $firstRow = false;
                $columns = array_keys((array) $row);
                foreach ($columns as $colOffset => $name) {
                    // Generalize column types for conversion later
                    $meta = $this->result->getColumnMeta($colOffset);
                    $type = $meta['native_type'];
                    if ($type == 'binary' && $meta['len'] + $meta['max_length'] == 32) {
                        $columnTypes[$name] = 'guid';
                    } elseif ($type == 'uniqueidentifier') {
                        $columnTypes[$name] = 'guid';
                    } elseif ($type == 'datetime' || $type == 'smalldatetime') {
                        $columnTypes[$name] = 'datetime';
                    } elseif ($type == 'bit' || $type == 'int') {
                        $columnTypes[$name] = 'int';
                    } elseif ($type == 'float' || $type == 'money') {
                        $columnTypes[$name] = 'float';
                    }
                }
            }

            // Loop each column that requires conversion
            // $columnTypes is ONLY columns of interest (those that need conversion)
            foreach ($columnTypes as $name => $type) {
                $value = $isObject ? $row->$name : $row[$name];
                if (isset($value)) {
                    if ($type == 'guid') {
                        $value = $this->binaryToGuid($value);
                    } elseif ($type == 'datetime') {
                        $value = date("Y-m-d H:i:s", strtotime($value));
                    } elseif ($type == 'int') {
                        $value = (int) $value;
                    } elseif ($type == 'float') {
                        $value = (float) $value;
                    }
                    if ($isObject) {
                        $row->$name = $value;
                    } else {
                        $row[$name] = $value;
                    }
                }
            }

            // Add row to $rows array
            $rows[] = $row;
            if ($first) {
                return $row;
            }
        }
        return $rows;
    }

    /**
     * Convert a mssql binary guid to a string guid
     * @param  binary string $binary
     * @return string
     */
    protected static function binaryToGuid($binary)
    {
        $unpacked = unpack('Va/v2b/n2c/Nd', $binary);
        return sprintf('%08X-%04X-%04X-%04X-%04X%08X', $unpacked['a'], $unpacked['b1'], $unpacked['b2'], $unpacked['c1'], $unpacked['c2'], $unpacked['d']);
        // Alternative: http://www.scriptscoop.net/t/c9bb02ec9fdb/decoding-base64-guid-in-python.html
        // Alternative: http://php.net/manual/en/function.ldap-get-values-len.php
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
        if (is_numeric($data)) {
            return $data;
        }
        if (!isset($data) or empty($data)) {
            return '';
        }
        $non_displayables = array(
            '/%0[0-8bcef]/',            // url encoded 00-08, 11, 12, 14, 15
            '/%1[0-9a-f]/',             // url encoded 16-31
            '/[\x00-\x08]/',            // 00-08
            '/\x0b/',                   // 11
            '/\x0c/',                   // 12
            '/[\x0e-\x1f]/'             // 14-31
        );
        foreach ($non_displayables as $regex) {
            $data = preg_replace($regex, '', $data);
        }
        $data = str_replace("'", "''", $data);
        return $data;
    }
}
