<?php
namespace X4\Classes;

/*  Установка опций

    $options['onPage']=10 объектов на странице
    $options['imagesIcon']=array('_GROUP'=>'folder.gif');
    $options['onRecord']=function($record) use ($params){}  // функция  выполняема на уровне трансформинга записи(все аттрибуты)
    $options['onPage']=отображать на странице
    $options['idAsNumerator']=Параметер для задания id
    $options['table']=таблица

   */


class FileJsonSource
{
    public  $_options;

    public function __construct()
    {
        $this->_options['allowTypes'] = array('css', 'htm', 'html');
    }


    public function setOptions($options)
    {
        $this->_options = $options;
    }


    public function createView($path = '/')
    {

        if ($this->_options['emulateRoot']) {
            $result['data_set']['rows'][1] = array('data' => $this->_options['emulateRoot']['data'], 'xmlkids' => 1, 'image' => $this->_options['emulateRoot']['image']);
            return $result;
        }

        $path = base64_decode($path);

        foreach (new \DirectoryIterator($path) as $fileInfo) {

            if ($fileInfo->isDot()) continue;
            $fileName = $fileInfo->getFilename();
            if ($fileInfo->isFile()) {
                $objType = '_FILE';
                $xmlkids = 0;
            } else {
                $objType = '_FOLDER';

                $xmlkids = 1;
            }

            $extData['$fileName'] = $fileName;

            $path = $fileInfo->getPath();
            
            $extData['size'] = \XFILES::formatSize($fileInfo->getSize());

            $encodedPath = base64_encode($path . '/' . $fileName);

            $r = array('id' => $encodedPath, 'xmlkids' => $xmlkids, 'image' => $this->_options['imagesIcon'][$objType], 'data' => array_values($extData), 'obj_type' => $objType);

            if ($objType == '_FILE') {
                $files['data_set']['rows'][$encodedPath] = $r;
            }

            if ($objType == '_FOLDER') {
                $folders['data_set']['rows'][$encodedPath] = $r;
            }

        }


        if ($folders['data_set']['rows']) $folders['data_set']['rows'] = array_reverse($folders['data_set']['rows']);


        if (!$files['data_set']['rows']) $files['data_set']['rows'] = array();
        if (!$folders['data_set']['rows']) $folders['data_set']['rows'] = array();
        $result['data_set']['rows'] = array_merge($folders['data_set']['rows'], $files['data_set']['rows']);


        if ($this->_options['onResultSet']) {

            if (!$result['data_set']['rows']) $result['data_set']['rows'] = $result['data'];

            $result['data_set']['rows'] = $this->_options['onResultSet']($result['data_set']['rows']);
        }


        return $result;
    }

}
