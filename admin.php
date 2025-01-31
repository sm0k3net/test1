<?php
session_start();
error_reporting(E_ERROR);

use X4\Classes\XRegistry;

require_once('boot.php');
xConfig::set('GLOBAL', 'currentMode', 'back');


XRegistry::set('ADM', $adm = new X4\AdminBack\AdminPanel());
XRegistry::get('EVM')->fire('AdminPanel:afterInit', array('instance' => $adm));
echo $adm->listen();
exit;
