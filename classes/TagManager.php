<?php

namespace X4\Classes;

class TagManagerPrototype
    extends \xSingleton
{
    public $exceptionHandled, $_TMS, $_PDO, $_EVM, $_tableName;

    public function __construct()
    {
        $this->_TMS = XRegistry::get('TMS');
        $this->_PDO = XRegistry::get('XPDO');
        $this->_EVM = XRegistry::get('EVM');
        $this->_tableName = 'tags';
    }

    public static function getTagsChosen($selected = null)
    {

        $list = self::getTagList();
        $list = \XARRAY::arrToKeyArr($list, 'id', 'tag');
        if (!empty($selected)) {
            $selected = array_combine($selected, $selected);
        }
        return \XHTML::arrayToXoadSelectOptions($list, $selected);

    }

    public static function getTagList($byModule = null)
    {
        $byModule = $byModule ? 'module="' . $byModule . '"' : '';
        return XPDO::selectIN('*', 'tags', $byModule);
    }

    public static function tagsToLine($arr)
    {
        if (!empty($arr)) return json_encode($arr);
    }

    public function setTag($module, $tag)
    {
        try {
            return XPDO::insertIN($this->_tableName, array
            (
                'id' => 'NULL',
                'module' => $module,
                'tag' => $tag
            ));
        } catch (\Exception $e) {
            $this->exceptionHandled = $e;
            return false;
        }
    }

    public function updateTag($id, $module, $tag)
    {
        try {
            return XPDO::updateIN($this->_tableName, $id, array
            (
                'module' => $module,
                'tag' => $tag
            ));
        } catch (\Exception $e) {
            $this->exceptionHandled = $e;
            return false;
        }
    }

    public function deleteTags($params)
    {

        if (is_array($params['id'])) {
            $id = implode($params['id'], "','");
            $w = 'id in (\'' . $id . '\')';
        } else {
            $w = 'id="' . $params['id'] . '"';
        }

        $query = 'delete from ' . $this->_tableName . ' where ' . $w;

        if ($this->_PDO->query($query)) {
            $this->result['deleted'] = true;
        }
    }

    public function removeTagById($id)
    {
        if (isset($id)) {
            return $this->_PDO->query('delete from ' . $this->_tableName . ' where id=' . $id);
        }
    }

    public function getTaggedModulesSelector($params)
    {

        $list = $this->getTaggedModulesList();
        $list = array_combine($list, $list);
        $this->result['data']['tagModuleSource'] = \XHTML::arrayToXoadSelectOptions($list, $params['currentModule']);

    }

    private function getTaggedModulesList()
    {
        return array('catalog', 'news');
    }

    public function getTagById($id, $idonly = false)
    {
        return $this->getTagByClmn('id', $id, $idonly);
    }

    private function getTagByClmn($clmn, $clmnVal, $idonly = false)
    {
        if (isset($clmnVal)) {
            $idonly = $idonly ? array('id') : '*';
            if (is_array($clmnVal)) {
                $where = $clmn . ' in ("' . implode($clmnVal, '","') . '")';
                return XPDO::selectIN($idonly, 'tags', $where);
            } elseif (is_int($clmnVal)) {
                $cval = XPDO::selectIN($idonly, 'tags', $clmnVal);

            } else {

                $cval = XPDO::selectIN($idonly, 'tags', $clmn . '="' . $clmnVal . '"');
            }
            if ($idonly) {
                return $cval[0]['id'];
            } else {
                return $cval;
            }
        }
    }

    public function getTagByName($name, $idonly = false)
    {
        return $this->getTagByClmn('tag', $name, $idonly);
    }

}


class TagManager
    extends TagManagerPrototype
{
    public function __construct()
    {
        parent::__construct();
    }


    public function getTagsChosenSelector($params)
    {
        $this->result['tagList'] = TagManagerPrototype::getTagsChosen($params['selected']);

    }

    public function tagsTable($params)
    {

        $source = new \X4\Classes\TableJsonSource();

        $opt = array
        (
            'onPage' => 200,
            'table' => 'tags',
            'order' => array
            (
                'id',
                'desc'
            ),
            'idAsNumerator' => 'id',
            'columns' => array
            (
                'id' => array(),
                'tag' => array(),
                'module' => array()
            )
        );

        $source->setOptions($opt);

        if (!$params['page']) $params['page'] = 1;

        $this->result = $source->createView($params['id'], $params['page']);

    }

    public function addTag($params)
    {
        $this->setTag($params['tagModuleSource'], $params['tag']);
    }


}
