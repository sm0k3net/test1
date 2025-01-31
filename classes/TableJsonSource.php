<?php

/*  Установка опций

    $options['onPage']=10 объектов на странице
    $options['imagesIcon']=array('_GROUP'=>'folder.gif');


    $options['columns']=array('>LastMod'=>                       //для параметров в params используем >paramName для структуры без стрелки
                               array('name'=>'LastModified',  //имя в визуальной модели если не задано берется имя заданное в параметрах
                                     'transformList'=>array('_GROUP'=>'og.gif');          // массив трансформинга переменной в случае если нужно преобразование данных
                                     'onAttribute'=>function($params,$value) use ($params) { return $transformedData}   //функция преобразования переменной , можно использовать базовые функции класса
                                     'onAttributeParams'=>array('')   //массив параметров для функции onattribute
                               );

    $options['onRecord']=function($record) use ($params){}  // функция  выполняема на уровне трансформинга записи(все аттрибуты)
    $options['onPage']=отображать на странице
    $options['idAsNumerator']=Параметер для задания id
    $options['table']=таблица

   */

namespace X4\Classes;

class TableJsonSource
{
    public static $fromTimeStamp;
    public static $cutWords;
    var $_options;

    //статическая базовая функция преобразование даты
    public  $_tree;
    //статичексая базовая функция обрезка до нужного количества слов
    public  $result;

    public function __construct()
    {
    }


    public function setOptions($options)
    {
        $this->_options = $options;
    }

    public function buildWhereFilter()
    {
        $filter = [];

        foreach ($this->_options['whereFilter'] as $item) {

            if (!empty($item[2])) {


                if (is_array($item[2]) && $item[1] == '=') {

                    $filter[] = $item[0] . ' in ("' . implode('","', $item[2]) . '")';

                } elseif ($item[1] == 'LIKE') {

                    $filter[] = $item[0] . ' LIKE "%' . $item[2] . '%"';
                } else {

                    $filter[] = $item[0] . $item[1] . $item[2];
                }
            }

        }


        return implode(' AND ', $filter);
    }

    public function createView($id = 1, $page = 1)
    {

        $PDO = XRegistry::get('XPDO');

        $currentPosition = ($page - 1) * $this->_options['onPage'];

        if ($this->_options['onPage']) {
            $limit = ' limit ' . $currentPosition . ' , ' . $this->_options['onPage'];
        }


        if ($this->_options['where']) {
            $where = ' where ' . $this->_options['where'];
        }

        if (!empty($this->_options['whereFilter'])) {

            $filter = $this->buildWhereFilter();

            if (!empty($filter)) $where = ' where ' . $filter;
        }


        if ($this->_options['order']) {
            $order = ' order by ' . $this->_options['order'][0] . ' ' . $this->_options['order'][1];
        }


        if (!$this->_options['customSqlQuery']) {
            $query = 'select `' . implode('`,`', array_keys($this->_options['columns'])) . '` from `' . $this->_options['table'] . '`' . $where . $order . $limit;

        } else {
            $query = $this->_options['customSqlQuery'] . $limit;

        }

        if (!isset($this->_options['nodeIdColumn'])) $this->_options['nodeIdColumn'] = 'id';

        $itemsCount = 0;

        //считаем количество страниц
        if ($this->_options['onPage'] && !$this->_options['customSqlQuery']) {

            $queryCount = 'select count(*) as itemsCount from `' . $this->_options['table'] . '`' . $where;
            $pdoResult = $PDO->query($queryCount);
            $row = $pdoResult->fetch(\PDO::FETCH_ASSOC);
            $itemsCount = $row['itemsCount'];

        } elseif ($this->_options['onPage'] && $this->_options['countQuery']) {

            $pdoResult = $PDO->query($this->_options['countQuery']);
            $row = $pdoResult->fetch(\PDO::FETCH_ASSOC);
            $itemsCount = $row['itemsCount'];

        }

        $result = false;
        $nodeId = 0;

        if ($pdoResult = $PDO->query($query)) {

            while ($row = $pdoResult->fetch(\PDO::FETCH_ASSOC)) {
                if (is_array($this->_options['columns'])) {
                    foreach ($this->_options['columns'] as $key => $tempValue) {
                        if (!isset($tempValue['name'])) $tempValue['name'] = $key;
                        $extData[$tempValue['name']] = $row[$key];

                        //трансформ по листу
                        if ($tempValue['transformList']) {
                            $extData[$tempValue['name']] = $tempValue['transformList'][$extData[$tempValue['name']]];
                        }

                        //трансформ по функции
                        if ($tempValue['onAttribute']) {
                            $extData[$tempValue['name']] = $tempValue['onAttribute']($tempValue['onAttributeParams'], $extData[$tempValue['name']]);
                        }
                    }


                    if (isset($this->_options['onRecord'])) {
                        $extData = $this->_options['onRecord']($extData);
                    }


                    if (isset($this->_options['idAsNumerator'])) {

                        $nodeId = $extData[$this->_options['idAsNumerator']];

                    } else {
                        $nodeId++;
                    }


                    if ($this->_options['vanillaFormat']) {
                        $result['data'][$nodeId] = $extData;
                    } elseif ($this->_options['gridFormat']) {

                        $r = array('id' => $nodeId, 'image' => $this->_options['imageIcon'], 'data' => array_values($extData), 'obj_type' => $this->_options['objType']);
                        $result['data_set']['rows'][$nodeId] = $r;

                    } else {

                        $result['data_set']['rows'][] = array('data' => array_values($extData));
                    }
                }

            }


            if ($this->_options['onResultSet']) {

                if (!$result['data_set']['rows']) $result['data_set']['rows'] = $result['data'];

                $result['data_set']['rows'] = $this->_options['onResultSet']($result['data_set']['rows']);
            }


            if ($this->_options['onPage']) {
                $result['pagesNum'] = ceil($itemsCount / $this->_options['onPage']);
            }


            return $result;
        }

    }


}

TableJsonSource::$fromTimeStamp = function ($params, $value) {
    return date($params['format'], $value);
};


TableJsonSource::$cutWords = function ($params, $value) {
    return \XSTRING::findnCutSymbolPosition($value, " ", $params['count']);
};

