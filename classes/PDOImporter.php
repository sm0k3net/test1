<?php

namespace X4\Classes;


class PDOImporter
{
    private static $keywords = array(
        'ALTER', 'CREATE', 'DELETE', 'DROP', 'INSERT',
        'REPLACE', 'SELECT', 'SET', 'TRUNCATE', 'UPDATE', 'USE',
        'DELIMITER', 'END'
    );


    public static function dropTables($connection)
    {
        $result=$connection->query('SHOW TABLES');
        while ($row = $result->fetch()) {
            $connection->exec("DROP TABLE IF EXISTS " . $row[0]);
        }

    }


    /**
     * Loads an SQL stream into the database one command at a time.
     *
     * @params $sqlfile The file containing the mysql-dump data.
     * @params $connection Instance of a PDO Connection Object.
     * @return boolean Returns true, if SQL was imported successfully.
     * @throws Exception
     */
    public static function importSQL($sqlfile, $connection)
    {

        # read file into array
        $file = file($sqlfile);

        # import file line by line
        # and filter (remove) those lines, beginning with an sql comment token
        $file = array_filter($file,
            create_function('$line',
                'return strpos(ltrim($line), "--") !== 0;'));

        # and filter (remove) those lines, beginning with an sql notes token
        $file = array_filter($file,
            create_function('$line',
                'return strpos(ltrim($line), "/*") !== 0;'));
        $sql = "";
        $del_num = false;
        foreach ($file as $line) {
            $query = trim($line);
            try {
                $delimiter = is_int(strpos($query, "DELIMITER"));
                if ($delimiter || $del_num) {
                    if ($delimiter && !$del_num) {
                        $sql = "";
                        $sql = $query . "; ";

                        $del_num = true;
                    } else if ($delimiter && $del_num) {
                        $sql .= $query . " ";
                        $del_num = false;

                        $connection->exec($sql);
                        $sql = "";
                    } else {
                        $sql .= $query . "; ";
                    }
                } else {
                    $pos=strrpos($query, ";");
                    $delimiter = is_int($pos);
                    $strlen=strlen($query);
                                  
                    if ($delimiter&&(($pos+1)==$strlen)) {
                        $connection->exec("$sql $query");
                        $sql = "";
                    } else {
                        $sql .= " $query";
                    }
                }
            } catch (\Exception $e) {
                echo $e->getMessage() . "<br /> <p>The sql is: $query</p>";
            }

        }
    }
}
