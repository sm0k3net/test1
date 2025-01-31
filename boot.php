<?php
require_once __DIR__ . '/x4/vendor/autoload.php';

require($_SERVER['DOCUMENT_ROOT'].'/x4/inc/core/helpers.php');
require($_SERVER['DOCUMENT_ROOT'].'/x4/inc/helpers/common.helper.php');

$generationTimeStart = Common::getmicrotime();

Common::includeAll($_SERVER['DOCUMENT_ROOT'].'/x4/inc/helpers',array('common.helper.php'));

X4Autoloader::init();

use X4\Classes\XRegistry;
use X4\Classes\MultiSection;
use X4\Classes\XPDO;
use X4\Classes\XNameSpaceHolder;
use X4\Classes\XEventMachine;

require($_SERVER['DOCUMENT_ROOT'].'/conf/init.php');

require(xConfig::get('PATH','CORE').'connector.core.php');
require(xConfig::get('PATH','CORE').'xModuleCommon.core.php');
require(xConfig::get('PATH','CORE').'xModulePrototype.core.php');
require(xConfig::get('PATH','CORE').'xListener.core.php');
require(xConfig::get('PATH','CORE').'xPlugin.core.php');
require(xConfig::get('PATH','CORE').'xPluginBack.core.php');
require(xConfig::get('PATH','CORE').'xModuleBack.core.php');
require(xConfig::get('PATH','CORE').'xModule.core.php');
require(xConfig::get('PATH','CORE').'xTpl.core.php');
require(xConfig::get('PATH','CORE').'xModuleApi.core.php');
require(xConfig::get('PATH','CORE').'xAction.core.php');
require(xConfig::get('PATH','CORE').'core.php');
require(xConfig::get('PATH','CORE').'pageAgregator.core.php');
require(xConfig::get('PATH','CORE').'helpers.tpl.php');
require(xConfig::get('PATH','XOAD').'xoad.php');


use Monolog\Logger;
use Monolog\Handler\StreamHandler;

function loggerStart(){

    $log = new Logger('general');
    $log->pushHandler(new StreamHandler(PATH_.'logs/general.log', Logger::INFO));
    xRegistry::set('logger',$log);

    $errLog = new Logger('error');
    $errLog->pushHandler(new StreamHandler(PATH_.'logs/error.log', Logger::INFO));
    xRegistry::set('errorLogger',$errLog);

}

Common::loadDriver('XCache', 'XCacheFileDriver');

loggerStart();

XRegistry::set('TMS',$TMS=new MultiSection());
XPDO::setSource(xConfig::get('DB','DB_HOST'),xConfig::get('DB','DB_NAME'),xConfig::get('DB','DB_USER'),xConfig::get('DB','DB_PASS'),'utf8',xConfig::get('DB','DB_PORT')); 
XRegistry::set('XPDO',XPDO::getInstance());

$enhance=new ENHANCE();
XRegistry::set('ENHANCE',$enhance);
XNameSpaceHolder::addObjectToNS('E',$enhance);

$debug=new DEBUG();
XRegistry::set('DEBUG',$debug);

XNameSpaceHolder::addObjectToNS('D',$debug);

$localize=new LOCALIZE();
XRegistry::set('LOCALIZE',$localize);
XNameSpaceHolder::addObjectToNS('L',$localize);

XRegistry::set('EVM',XEventMachine::getInstance());

xCore::pluginEventDetector();
xCore::moduleEventDetector();
