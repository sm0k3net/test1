<?php
error_reporting(0);

require('../../boot.php');  

header('Content-Type: text/html; charset=utf-8');

$mods=XFILES::directoryList(xConfig::get('PATH','MODULES'));

foreach($mods as $mod)
{
    include($mod.'/lang/rus.lang.php'); 
    $fileRus=$LANG;
    include($mod.'/lang/eng.lang.php'); 
    $fileEng=$LANG;
    
    
    echo $mod."\r\n";
    $diff=array_diff_key ($fileRus,$fileEng);
    print_r($diff);
}