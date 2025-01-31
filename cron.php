<?php
ignore_user_abort(1); 
use X4\Classes\XRegistry;
use X4\Classes\XEventMachine;

error_reporting(0);

require_once('boot.php');
require_once(xConfig::get('PATH','ADMBACK') . 'adm.class.php');

XRegistry::get('EVM')->fire('cronBoot');
xConfig::set('GLOBAL','currentMode','cron');

XRegistry::set('EVM',XEventMachine::getInstance());
$instance=xCore::loadCommonClass('tasks');
$instance->getCurrentTasks();
//$adm=new AdminPanel();
//$adm->clearCache(true);

?>