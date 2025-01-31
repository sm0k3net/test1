<?php
error_reporting(E_ERROR|E_PARSE);
use X4\Classes\XRegistry;
use Symfony\Component\Console\Application;

$_SERVER['DOCUMENT_ROOT']=__DIR__;
$_SERVER['CONSOLE']=true;
require_once('boot.php');
xConfig::set('GLOBAL', 'currentMode', 'console');

$application = new Application();

if(!empty(xListener::$commandsRegistry)){
        $application->addCommands(xListener::$commandsRegistry);
}
$application->run();

