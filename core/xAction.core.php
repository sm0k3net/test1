<?php

class xAction
{
    private $execModule = null;
    private $moduleInstance = null;
    private $actionModuleDataCache=array();
    public  $actionKey;

    public function __construct($moduleName,$preventParentInit=false)
    {
        $this->setParentModule($moduleName);
        
        if(!$preventParentInit)
        {
            $this->moduleInstance = xCore::moduleFactory($this->execModule.'.front');
        }
    }
    
    public function __get($key)
    {
        return $this->moduleInstance->$key;
    }
    
    
    public function __isset($key)
    {
        return isset($this->moduleInstance->$key);     
    }
    

    public function __set($key, $value)
    {
        $this->moduleInstance->$key = $value;
    }

    public function setParentModule($moduleName)
    {
        $this->execModule = $moduleName;
    }

    final public function __call($chrMethod, $arrArguments)
    {
        return call_user_func_array(array($this->moduleInstance, $chrMethod), $arrArguments);
    }
    
    public function getDataCache($key)
    {
        if(empty($key)){return $this->actionModuleDataCache;}
        return $this->actionModuleDataCache[$key];
    }
    
    public function setActionKey($key)
    {
            $this->actionKey=$key;
    }

    public function setDataCache($key,$value)
    {
        $this->actionModuleDataCache[$key]=$value;
        
    }
}
