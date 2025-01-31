<?php

namespace X4\Classes;

class XPDOExceptionHandler
{
    public function __construct(\PDOException $e)
    {
        if (method_exists($this, $method = 'code' . $e->getCode())) {
            return call_user_func_array(array(
                $this,
                $method
            ), array(
                $e
            ));
        } else {
            die($e->getMessage());
        }
    }

    public function code1045()
    {
        die('could not connect to database login and password wrong');
    }
}


class XPDO extends \PDO
{
    private static $objInstance;
    private static $host;
    private static $dbname;
    private static $user;
    private static $password;
    private static $encoding;
    private static $port;
    public static $lastInserted;

    public function __construct()
    {
    }

    private function __clone()
    {
    }

    /*
     * создание PDO соединения
     * @param
     * @return $objInstance;
     */
    public static function setSource($host, $dbname, $user, $password, $encoding = 'utf8', $port = 3306)
    {
        self::$host = $host;
        self::$dbname = $dbname;
        self::$user = $user;
        self::$password = $password;
        self::$encoding = $encoding;
        self::$port = $port;
        self::$lastInserted = null;
    }

    public static function getInstance()
    {
        if (!self::$objInstance) {
            try {
                $conn = 'mysql:host=' . self::$host . ';port=' . self::$port . ';dbname=' . self::$dbname;
                self::$objInstance = new \PDO('mysql:host=' . self::$host . ';port=' . self::$port . ';dbname=' . self::$dbname, self::$user, self::$password);
                self::$objInstance->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            } catch (\PDOException $e) {
                new XPDOExceptionHandler($e);
            }

            self::$objInstance->exec('SET CHARACTER SET ' . self::$encoding);
            self::$objInstance->exec('set character_set_results=' . self::$encoding);
            self::$objInstance->exec('SET NAMES ' . self::$encoding);
        }

        return self::$objInstance;
    } 


    /**
     * Получить название колонок
     * @param $table
     * @return $mix
     */
    private static function getColumnNames($table)
    {
        static $tables = array();
        if (isset($tables[$table])) {
            return $tables[$table];
        }

        $PDO = self::$objInstance;
        $sql = 'SHOW COLUMNS FROM ' . $table;
        $stmt = $PDO->prepare($sql);
        $columnNames = array();
        try {
            if ($stmt->execute()) {
                while ($rawColumnData = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    if ($rawColumnData['Field'] != 'id') {
                        $columnNames[$rawColumnData['Field']] = '';
                    } else {
                        $columnNames[$rawColumnData['Field']] = 'NULL';
                    }
                }

                return $tables[$table] = $columnNames;
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    static function escapeInner($data)
    {
        $data = str_replace("\\", "\\\\", $data);
        $data = str_replace("'", "\'", $data);
        $data = str_replace('"', '\"', $data);
        $data = str_replace("\x00", "\\x00", $data);
        $data = str_replace("\x1a", "\\x1a", $data);
        $data = str_replace("\r", "\\r", $data);
        $data = str_replace("\n", "\\n", $data);
        return ($data);

    }


    /**
     * выбрать данные из таблицы(нескольких таблиц)
     * @param  array $select - список параметров
     * @param  string $from - таблицы
     * @param  string $where - условие
     * @return array
     */
    public static function selectIN($select = '*', $from, $where = '', $specialCond = '')
    {
        $PDO = self::$objInstance;

        if (is_array($select)) {
            $select = '`' . implode('`,`', $select) . '`';
        }

        if (is_array($where)) {
            $where = array_unique($where);
            $where = 'where id in ("' . implode('","', $where) . '")';
        } elseif (is_int($where)) {
            $where = 'where id=' . $where;
        } elseif ($where) {
            $where = 'where ' . $where;
        }

        $query = "select $select from $from $where  $specialCond";

        if ($result = $PDO->query($query)) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }
    }


    public static function deleteIN($from, $where = '')
    {
        $PDO = self::$objInstance;

        if (is_array($where)) {
            $where = array_unique($where);
            $where = 'where id in ("' . implode('","', $where) . '")';
        } elseif (is_int($where)) {
            $where = 'where id=' . $where;
        } elseif (!empty($where)) {
            $where = 'where ' . $where;
        }

        $query = "delete from $from $where";

        if ($result = $PDO->query($query)) return true;
    }


    public static function getLastInserted()
    {
        self::$lastInserted = self::$objInstance->lastInsertId();
        return self::$lastInserted;

    }

    /**
     * вставить данные в таблицу
     * @param  string $table - название таблицы
     * @param array $insertVals -  ассоциативный массив значений
     * @return bool
     */
    public static function insertIN($table, $insertVals)
    {
        if($table == 'routes' && !isset($insertVals['full']))
        {//Баг возникает при сохранении данных на страницу
            $insertVals['full'] = 0;
        }
        $PDO = self::$objInstance;
        $checkFields = self::getColumnNames($table);
        foreach ($insertVals as $key => $val) {
            if (array_key_exists($key, $checkFields)) {
                $checkFields[$key] = self::escapeInner($val);
            }
        }

        $values = implode("','", array_values($checkFields));

        $query = "INSERT INTO `$table` (" . '`' . implode('`,`', array_keys($checkFields)) . '`' . ")VALUES(" . "'" . $values . "'" . ')';

        $query = str_replace(array(
            "'NOW()'",
            "'null'",
            "'NULL'"
        ), array(
            'NOW()',
            'null',
            'NULL'
        ), $query);

        if ($PDO->exec($query)) {

            return self::getLastInserted();
        }
    }

    /**
     * обновить данные в таблице
     * @param  string $table - название таблицы
     * @param  mixed $express выражение либо число в случае целого числа оно трактуется как параметр ID
     * @return bool
     */
    public static function updateIN($table, $express, $updateVals)
    {
        $PDO = self::$objInstance;
        if (is_int($express)) {
            $express = "`id` = '$express' LIMIT 1";
        }

        $updateline = '';

        foreach ($updateVals as $key => $val) {
            $val = self::escapeInner($val);
            $updateline .= "`$key` = '$val',";
        }

        $updateline = substr($updateline, 0, strlen($updateline) - 1);

        $query = "UPDATE `$table` SET $updateline WHERE $express";
        $ex = $PDO->exec($query);
        if ($ex !== false) {
            return true;
        }
    }


    public static function multiInsertIN($tableName, $data, $replaceMode = false)
    {

        $pdoObject = self::$objInstance;
        //Will contain SQL snippets.
        $rowsSQL = array();

        //Will contain the values that we need to bind.
        $toBind = array();

        $columnNames = array_keys(current($data));

        //Loop through our $data array.
        foreach ($data as $arrayIndex => $row) {

            $params = array();

            foreach ($row as $columnName => $columnValue) {
                $param = ":" . str_replace('.', '__', $columnName) . $arrayIndex;
                $params[] = $param;
                $toBind[$param] = $columnValue;
            }
            $rowsSQL[] = "(" . implode(", ", $params) . ")";
        }

        if (!$replaceMode) {
            $type = 'INSERT';

        } else {

            $type = 'REPLACE';
        }

        //Construct our SQL statement
        $sql = "{$type} INTO `$tableName` (`" . implode("`,`", $columnNames) . "`) VALUES " . implode(", ", $rowsSQL);

        //Prepare our PDO statement.
        $pdoStatement = $pdoObject->prepare($sql);

        //Bind our values.
        foreach ($toBind as $param => $val) {
            $pdoStatement->bindValue($param, $val);
        }


        try {
            //Execute our statement (i.e. insert the data).
            return $pdoStatement->execute();


        } catch (PDOException $Exception) {

            $t = 1;
        }
    }


}

