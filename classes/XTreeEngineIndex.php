<?php
namespace X4\Classes;

class XTreeEngineIndex
{
    private $treeIndexName;
    private $types = array();
    public $tree;

    public function __construct($tree)
    {
        $this->tree=$tree;
    }


    public function createIndex()
    {
        $this->treeIndexName = strtolower("_tree_" . $this->tree->treeName . "_index");
        $columns = $this->getParametersList();
        $this->createIndexTable($columns);
        $this->pushIndexData();
    }

    public function setupType($param, $type)
    {
        $param = $this->sanitizeFieldName($param);
        $this->types[$param] = $type;
    }

    public function getParametersList()
    {
        $query = "select parameter from `{$this->tree->treeParamName}` group by parameter";
        $pdoResult = $this->tree->PDO->query($query);

        while ($row = $pdoResult->fetch(\PDO::FETCH_ASSOC)) {
            $columns[] = $this->sanitizeFieldName($row['parameter']);
        }

        return $columns;
    }

    private function sanitizeFieldName($row)
    {
        return str_replace('.', '___', $row);
    }

    public function createIndexTable($columns)
    {
        $types = $this->types;
        $fields = array_reduce($columns, function ($str, $v) use ($types) {

            if (!empty($types[$v])) {
                $type = $types[$v];
                $str .= "`$v` $type DEFAULT NULL,";
            }

            return $str;
        });

        $this->tree->PDO->query('DROP TABLE IF EXISTS `' . $this->treeIndexName . '`');
        $query = "CREATE TABLE `{$this->treeIndexName}` (`_id_` INT( 14 ) UNSIGNED NOT NULL AUTO_INCREMENT,        
        `_nodeid_` INT( 14 ) UNSIGNED, 
        `_path_` VARCHAR(256),
        {$fields} PRIMARY KEY (`_id_`)) ENGINE=MyISAM DEFAULT CHARSET=utf8";
        try {
            $result = $this->tree->PDO->exec($query);

        } catch(PDOException $e) {
            echo $e->getMessage();
        }
    }

    public function pushIndexData()
    {
        $treeData = $this->tree->selectStruct('*')->selectParams('*')->run();
        if (!empty($treeData)) {
            foreach ($treeData as $data) {
                $dataRow = array();
                $setup=false;
                $dataRow=array_fill_keys(array_keys($this->types), 'NULL');
                $dataRow['_id_'] = 'NULL';
                $dataRow['_nodeid_'] = $data['id'];
                $dataRow['_path_'] = implode('.',$data['path']);

                foreach ($data['params'] as $key => $val) {
                    $key=$this->sanitizeFieldName($key);

                    if(!empty($this->types[$key]))
                    {
                        if(empty($val)){
                            $val=0;
                        }

                        $dataRow[$key]=intval($val);
                        $setup=true;
                    }
                }

                if($setup) {
                    XPDO::insertIN($this->treeIndexName, $dataRow);
                }
            }
        }
    }
}