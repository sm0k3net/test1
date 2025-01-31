<?php

error_reporting(E_ALL);
session_start();

use X4\Classes\XRegistry;

require('boot.php');




$module=xCore::moduleFactory('catalog.front');
$index= new X4\Classes\XTreeEngineIndex($module->_commonObj->_tree);

$index->setupType('bide.width','INT(13)');
$index->setupType('geometry.width','INT(13)');
$index->setupType('geometry.length','INT(13)');
$index->setupType('geometry.height','INT(13)');
$index->setupType('__nodeChanged','INT(13)');
$index->setupType('unitazy.length','INT(13)');
$index->setupType('unitazy.width','INT(13)');
$index->setupType('unitazy.width','INT(13)');

$index->createIndex();


$index= new X4\Classes\XTreeEngineIndex($module->_commonObj->_sku);

$index->setupType('length','INT(13)');
$index->setupType('width','INT(13)');

$index->createIndex();
