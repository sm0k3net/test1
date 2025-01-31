<?php

use X4\Classes\XNameSpaceHolder;
use X4\Classes\XRegistry;
use X4\Classes\MultiSection;

class xPlugin
    extends xSingleton
{
    private $modules;
    public $module;
    public $_config;

    public function __construct($moduleInstance)
    {
        $this->_TMS = new MultiSection();
        $this->_PDO = XRegistry::get('PDO');
        $this->_EVM = XRegistry::get('EVM');

        $dir = dirname($moduleInstance);
        $basename = basename($dir);
        $baseExploded = explode('.', $basename);
        $this->setInstance(xCore::moduleFactory($baseExploded[0].'.front'));
    }

    public function setInstance($moduleInstance)
    {
        $this->_module = $moduleInstance;
    }

    public function setListener($listenerInstance)
    {
        $this->listenerInstance = $listenerInstance;
    }
    
    public function  setConfig($config=null)
    {
       $this->_config=$config;
    }

    public function modulesRequire($modules)
    {
        foreach ($modules as $module) {
            xCore::moduleFactory($module.'.front');
        }
    }
}
