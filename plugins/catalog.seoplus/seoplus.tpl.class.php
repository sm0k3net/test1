<?php
use X4\Classes\xRegistry;
class seoplusTpl extends xTpl implements xPluginTpl
{
    public function __construct(){}
    

    public function getSeoRule()
    {
        return seoplusListener::getRule($_SERVER['REDIRECT_URL']);
    }
    
}
