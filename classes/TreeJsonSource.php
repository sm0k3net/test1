<?php

/*  Установка опций



    $options['showNodesWithObjType'] - массив нод разрешенных для показа с определенным objType
    $options['showNodesAsParents']=array(); - массив нод которые будут трактоваться как предки 
    $options['emulateRoot']=array('data'=>'','image');
    $options['endLeafs'] ->array('OBJTYPE')
    $options['onPage']=10 объектов на странице
    $options['imagesIcon']=array('_GROUP'=>'folder.gif');


    $options['columns']=array('>LastMod'=>                       //для параметров в params используем >paramName для структуры без стрелки
                               array('name'=>'LastModified',  //имя в визуальной модели если не задано берется имя заданное в параметрах
                                     'transformList'=>array('_GROUP'=>'og.gif');          // массив трансформинга переменной в случае если нужно преобразование данных
                                     'onAttribute'=>function($params,$value) use ($params) { return $transformedData}   //функция преобразования переменной , можно использовать базовые функции класса
                                     'onAttributeParams'=>array('')   //массив параметров для функции onattribute
                               );

    $options['onRecord']=function($record) use ($params){}  // функция  выполняема на уровне трансформинга записи(все аттрибуты)


    $this->_options['limitDown']= интервал начала выборки
    $this->_options['count'] = количество(шаг) выборки
   */

namespace X4\Classes;

class TreeJsonSource
{
    public  $_options;
    public  $_tree;
    public  $result;

    //статичексая базовая функция преобразование даты
    public static $fromTimeStamp;
    //статичексая базовая функция обрезка до нужного количества слов
    public static $cutWords;

    public function __construct($tree)
    {
        $this->_tree = $tree;
    }


    public function setOptions($options)
    {
        $this->_options = $options;
    }


    public function createView($id = 1, $page = null, $levels = 1)
    {

        if ($this->_options['emulateRoot'] && $id == 0) {
            $result['data_set']['rows'][1] = array('data' => $this->_options['emulateRoot']['data'], 'xmlkids' => 1, 'image' => $this->_options['emulateRoot']['image']);
            return $result;
        }


        if ($this->_options['showNodesWithObjType']) $addWhere = array(array('@obj_type', '=', $this->_options['showNodesWithObjType']));

        if ($id == 0) {
            $curId = 1;
            $lev = 1;
        } else {
            $curId = $id;
            $lev = 2;
        }


        if ($this->_options['onPage']) {
            //   $counter=count($nodes  =$this->_tree->selectStruct('*')->childs($id,1)->where($addWhere,true)->run());
        }

        if ($page) {
            $currentPosition = ($page - 1) * $this->_options['onPage'];
        }


        $nodes = $this->_tree->getDisabled()->selectParams('*')->selectStruct('*')->childs($id, $levels)
            ->where($addWhere, true)->limit($currentPosition, $this->_options['onPage']);

        if (!empty($this->_options['sortby'])) {
            $nodes->sortby($this->_options['sortby'][0], $this->_options['sortby'][1]);
        }

        $nodes = $nodes->run();

        if (!empty($nodes)) {
            $nodesCount = $this->_tree->nodesAllCount;
            $nodesStrip = \XARRAY::asKeyVal($nodes, 'id');
            $childsNodes = $this->_tree->childNodesExist($nodesStrip, $nodes[0]['ancestorLevel'], $this->_options['showNodesWithObjType']);
            foreach ($nodes as $node) {
                $nodeId = $node['id'];
                if (is_array($this->_options['columns'])) {

                    foreach ($this->_options['columns'] as $key => $tempValue) {

                        if ($key[0] == '>') {
                            $paramedKey = true;
                            $key = substr($key, 1);
                        } else {
                            $paramedKey = false;
                        }

                        //замена имени
                        if (!$tempValue['name']) {
                            $tempValue['name'] = $key;
                        }

                        $extData[$tempValue['name']] = ($paramedKey) ? $node['params'][$key] : $node[$key];

                        //трансформ по листу
                        if ($tempValue['transformList']) {
                            $extData[$tempValue['name']] = $tempValue['transformList'][$extData[$tempValue['name']]];
                        }


                        //трансформ по функции
                        if ($tempValue['onAttribute']) {
                            $extData[$tempValue['name']] = $tempValue['onAttribute']($tempValue['onAttributeParams'], $extData[$tempValue['name']], $nodeId);
                        }


                    }


                    if (!empty($this->_options['onRecord'])) {
                        $extData = $this->_options['onRecord']($extData);
                    }

                    if (!empty($this->_options['zeroLead'])) {
                        $idRevert = '0' . $nodeId;
                    } else {
                        $idRevert = $nodeId;
                    }

                    if ($this->_options['vanillaFormat']) {
                        $result['data'][$nodeId] = $extData;
                    } elseif ($this->_options['gridFormat']) {


                        $r = array('id' => $idRevert, 'image' => $this->_options['imagesIcon'][$node['obj_type']], 'data' => array_values($extData), 'obj_type' => $node['obj_type']);

                        if (in_array($node['obj_type'], $this->_options['showNodesAsParents'])) {
                            $r['xmlkids'] = 1;
                        }

                        if (in_array($nodeId, $childsNodes)) {
                            $r['xmlkids'] = 1;
                        }

                        $result['data_set']['rows'][$idRevert] = $r;

                    } else {


                        $result['data_set']['rows'][$idRevert] = array('data' => array_values($extData));
                    }
                }

            }

            if ($this->_options['onPage']) {
                $result['pagesNum'] = ceil($nodesCount / $this->_options['onPage']);
            }

            return $result;
        }
    }


} //endclass


TreeJsonSource::$fromTimeStamp = function ($params, $value, $id) {
    return date($params['format'], $value);
};


TreeJsonSource::$cutWords = function ($params, $value) {
    return XSTRING::findnCutSymbolPosition($value, " ", $params['count']);
};
