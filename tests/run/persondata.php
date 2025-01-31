<?php
require('../../boot.php');  

require(xConfig::get('PATH','MODULES').'xpe/ext/personData.php');       
require(xConfig::get('PATH','MODULES').'xpe/ext/personDataModel.php');       
require(xConfig::get('PATH','MODULES').'xpe/ext/personDataField.php');    
require(xConfig::get('PATH','MODULES').'xpe/ext/pipelineField.php');       
require(xConfig::get('PATH','MODULES').'xpe/ext/staticField.php');       
require(xConfig::get('PATH','MODULES').'xpe/ext/personStorage.php');   

$storage=personStorage::getInstance();
$storageDriver=$storage->factorStorage('file',array('storagePath'=>xConfig::get('PATH','MEDIA').'persons/'));


/*$person = new personDataModel('4324-2342-444');
$person->addGroup('static');
$person->addField('static', new staticField('test','test',1));
$person->addField('static', new staticField('test2','test2',2));

$pipeTest=new pipelineField('dummyname','dummyALias');
$pipeTest->setSource('dummy');

$person->addGroup('pipelines');
$person->addField('pipelines',$pipeTest);
*/
$P=new personData();
$P->setStorage($storageDriver);

debugbreak();
$P->load('4324-2342-444');