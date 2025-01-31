<?php

namespace X4\Classes;

class importer
{
    public $options;
    public $modifiersFunctions;
    public $columnModifiers;
    public $chunksize = 500;
    public $loadCallBack = null;
    public $iteration;
    public $tableName = '';

    public function __construct($importer, $file, $options)
    {
        $this->importer = $importer;
        $this->options = $options;
        $this->file = $file;
        $this->iteration = 0;

    }

    public function createTempImportTable()
    {

        $fields = array_reduce(array_keys($this->columnModifiers), function ($str, $v) {
            $str .= "`$v` TEXT, ";
            return $str;
        });


        $this->importer->_PDO->query('DROP TABLE IF EXISTS `' . $this->tableName . '`');

        $query = "CREATE TABLE `{$this->tableName}` (`id` INT( 12 ) UNSIGNED NOT NULL AUTO_INCREMENT ,`status` varchar(128) DEFAULT NULL , `internalId` INT( 12 ) UNSIGNED DEFAULT NULL, {$fields} PRIMARY KEY (`id`)) ENGINE=MyISAM DEFAULT CHARSET=utf8";

        $this->importer->_PDO->query($query);


    }

    public function addColumnCopy($nameColumn, $oldNameColumn)
    {
        $query = "ALTER TABLE `{$this->tableName}` ADD `{$nameColumn}` TEXT NOT NULL ";
        $this->importer->_PDO->exec($query);

        $query = "UPDATE `{$this->tableName}` SET `{$nameColumn}` = `{$oldNameColumn}` WHERE `id`=`id`";
        $this->importer->_PDO->exec($query);


        $this->columnModifiers[$nameColumn] = $this->columnModifiers[$oldNameColumn];
        $this->columnModifiers[$nameColumn]['realName'] = $nameColumn;

    }

    public function setCallLoadCallback($callback)
    {
        $this->loadCallBack = $callback;
    }

    public function setInternalIds($list)
    {
        XPDO::multiInsertIN($this->tableName, $list, true);
    }

    public function setTableName($table)
    {
        $this->tableName = $table . '_importer';
    }

    public function insertData($data)
    {

        if ($dataChunked = array_chunk($data, $this->chunksize)) {
            foreach ($dataChunked as $chunk) {
                $this->iteration++;

                XPDO::multiInsertIN($this->tableName, $chunk);

                if (!empty($this->loadCallBack)) {
                    $this->loadCallBack->__invoke($this->iteration, $this->chunksize);
                }

            }
        }

    }


}

class csvImporter extends importer
{
    public $removeIndexes;
    public $csvParser;
    public $customTitles;

    public function __construct($importer, $file, $options)
    {

        parent::__construct($importer, $file, $options);

        $this->csvParser = new ParseCSV();
        $this->csvParser->delimiter = isset($this->options['delimiter']) ? $this->options['delimiter'] : ';';
        $this->csvParser->offset = isset($this->options['offset']) ? $this->options['offset'] : 0;
        $this->csvParser->limit = isset($this->options['limit']) ? $this->options['limit'] : 200000;

    }

    public function getTitles()
    {
        $this->columnModifiers = \XCacheFileDriver::serializedRead($this->options['cacheName']);
    }


    public function setCustomTitles($titlesArray)
    {
        $this->customTitles = $titlesArray;
    }

    public function preConstruct()
    {

        $this->csvParser->parse($this->file);

        if (!isset($this->csvParser->data))
            return false;

        if ($this->csvParser->offset == 0) {
            $titles = $this->titleConstructor();
            $this->serializeTitles();
            $this->setTableName('csv');
            $this->createTempImportTable();
        }


        $this->prepareData();
        $this->insertData($this->csvParser->data);
        return true;

    }

    private function loadCustomTitles()
    {

        if (!empty($this->customTitles)) {
            foreach ($this->customTitles as $key => $title) {

                if ($title) {

                    $this->columnModifiers[$key] = $title;
                    $this->columnModifiers[$key]['realName'] = $key;

                    if (isset($title['required'])) {
                        $this->required[] = $key;
                    }
                } else {

                    $this->removeIndexes[] = $key;
                }
            }
        }
    }

    public function titleConstructor()
    {

        if (!empty($this->customTitles)) {
            $this->loadCustomTitles();
            return;
        }

        if ($this->csvParser->titles) {

            foreach ($this->csvParser->titles as $id => $title) {
                //$data = \XCODE::jsonDecode($title, true);
                $data = array("$title" => $title);

                if ($data) {
                    $key = key($data);
                    if (isset($data[$key]['option'])) {
                        $keyName = $key . '.' . $data[$key]['option'];
                    } else {
                        $keyName = $key;
                    }

                    $this->columnModifiers[$keyName] = $data[$key];
                    $this->columnModifiers[$keyName]['realName'] = $key;

                    if (isset($data[$key]['required'])) {

                        $this->required[] = $keyName;
                    }
                } else {

                    $this->removeIndexes[] = $id;
                }
            }

        } else {

            throw new Exception('import-titles-are-empty');
        }

    }

    public function serializeTitles()
    {
        \XCacheFileDriver::serializedWrite($this->options['cacheName'], $this->columnModifiers, false);
    }

    public function prepareData()
    {

        $keys = array_keys($this->columnModifiers);


        foreach ($this->csvParser->data as $key => &$dta) {
            if (isset($this->removeIndexes)) {
                foreach ($this->removeIndexes as $index) {
                    unset($dta[$index]);
                }
            }

            $dtaTemp = $dta;

            $filtered = array_filter($dtaTemp);

            if (count($filtered) == 0) {

                unset($this->csvParser->data[$key]);
                continue;
            }


            if (count($dta) == count($keys)) {
                $dta = array_combine($keys, $dta);

            } else {

                unset($this->csvParser->data[$key]);
                continue;
            }

            $dta['id'] = 0;


            if (isset($this->required)) {


                $unsetRow = false;
                foreach ($this->required as $require) {
                    if ($this->csvParser->data[$key][$require] == "") {
                        $unsetRow = true;
                        break;
                    }
                }


                if ($unsetRow) {
                    unset($this->csvParser->data[$key]);
                }

            }


        }

    }
}


class xlsxImporter extends importer
{
    var $removeIndexes;
    var $reader;

    public function __construct($importer, $file, $options)
    {

        parent::__construct($importer, $file, $options);
        $this->reader = \Common::getXLSParser($file);
        $this->reader->ChangeSheet(0);

    }

    public function getTitles()
    {
        $this->columnModifiers = \XCacheFileDriver::serializedRead($this->options['cacheName']);
    }

    public function preConstruct()
    {
        $this->titleConstructor();
        $this->serializeTitles();
        $this->setTableName('xlsx');
        $this->createTempImportTable();
        $this->prepareData();
        unset($this->data[0]);
        $this->insertData($this->data);
        return true;

    }

    public function titleConstructor()
    {
        $titles = $this->reader->current();
        if ($titles) {

            $titles = array_filter($titles);

            foreach ($titles as $id => $title) {
                $data = \XCODE::jsonDecode($title, true);

                if ($data) {
                    $key = key($data);
                    if ($data[$key]['option']) {
                        $keyName = $key . '.' . $data[$key]['option'];
                    } else {
                        $keyName = $key;
                    }

                    $this->columnModifiers[$keyName] = $data[$key];
                    $this->columnModifiers[$keyName]['realName'] = $key;

                    if ($data[$key]['required']) {

                        $this->required[] = $keyName;
                    }
                } else {

                    $this->removeIndexes[] = $id;
                }
            }

        } else {

            throw new Exception('import-titles-are-empty');
        }

    }

    public function serializeTitles()
    {
        \XCacheFileDriver::serializedWrite($this->options['cacheName'], $this->columnModifiers, false);
    }

    public function prepareData()
    {

        $keys = array_keys($this->columnModifiers);


        $keysCount = count($keys);

        foreach ($this->reader as $key => $dta) {
            if (isset($this->removeIndexes)) {
                foreach ($this->removeIndexes as $index) {
                    unset($dta[$index]);
                }
            }

            $dtaTemp = $dta;

            $filtered = array_filter($dtaTemp);
            $dta = array_values($dta);
            if (count($filtered) == 0) {
                continue;
            }

            if (count($dta) < $keysCount) {

                for ($i = 0; $i < $keysCount; $i++) {
                    if (!isset($dta[$i])) {
                        $dta[$i] = '';
                    }
                }

            } elseif (count($dta) > $keysCount) {
                $dta = array_slice($dta, 0, $keysCount);
            }


            $dta = array_combine($keys, $dta);
            $dta['id'] = 'NULL';

            if (isset($this->required)) {
                $unsetRow = false;

                foreach ($this->required as $require) {
                    if ($dta[$require] == "") {
                        $unsetRow = true;
                        break;
                    }
                }


                if (!$unsetRow) {
                    $this->data[$key] = $dta;
                }

            } else {
                $this->data[$key] = $dta;
            }


        }

    }
}

class XImport extends \xSingleton
{
    public $processor;
    protected $columnsModifiersFuncs;

    public function __construct()
    {

        $this->_PDO = XRegistry::get('XPDO');
        $this->_EVM = XRegistry::get('EVM');
    }

    public function getColumnModifiersByType($type)
    {
        static $out;

        if (!isset($out)) {
            foreach ($this->processor->columnModifiers as $name => $modifier) {
                $out[$modifier['type']][$name] = $modifier;
            }

        }

        if (isset($type)) {
            return $out[$type];

        }

        return $out;

    }

    public function getColumnModifiers()
    {
        return $this->processor->columnModifiers;
    }

    public function addColumnModifier($name, $columnFunc)
    {

        $this->columnsModifiersFuncs[$name] = $columnFunc;

    }

    public function setInternalIds($list)
    {
        $this->processor->setInternalIds($list);
    }

    public function initiateProcessor($file, $options)
    {
        $type = $this->detectType($file);
        $class = 'X4\Classes\\' . $type . 'Importer';
        $this->processor = new $class($this, $file, $options);

    }

    public function processTableImport($file, $options = array())
    {
        if (!$this->processor) {
            $this->initiateProcessor($file, $options);
        }
        return $this->processor->preConstruct();
    }

    public function detectType($file)
    {
        $parts = pathinfo($file);
        return $parts['extension'];

    }

}
