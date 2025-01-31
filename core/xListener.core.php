<?php

class xListener
{
    public $execClassName;
    public $_EVM;
    public $useModuleTplNS;
    public $useModuleXfrontNS;
    public $_config;
    static $commandsRegistry;

    public function __construct($name)
    {
        $this->_EVM = X4\Classes\XRegistry::get('EVM');
        $this->execClassName = $name;

        if (!empty($_SERVER['CONSOLE'])) {
            $this->setupCommands();
        }


    }

    private function registryConsoleRun($declaredCommands,$initCommonClass)
    {
        if (!empty($declaredCommands)) {

            $classes = reset($declaredCommands);
            foreach ($declaredCommands as $commandClass) {
                if(!strstr($commandClass,'Symfony\\')&&strstr($commandClass,'Command')) {

                    $decClass=new $commandClass;

                    if(!empty($initCommonClass)){
                        $decClass->_commonObj=xCore::loadCommonClass($initCommonClass);
                    }

                    self::$commandsRegistry[] = $decClass;
                }
            }

        }
    }

    public function setupCommands()
    {
          $classes = get_declared_classes();

        if (strstr($this->execClassName, '.')) {

            xCore::pluginFactory($this->execClassName . '.command');
            $declaredCommands = array_diff(get_declared_classes(), $classes);
            $this->registryConsoleRun($declaredCommands,false);
        }else{

            try {

                xCore::moduleFactory($this->execClassName . '.command');
                $declaredCommands = array_diff(get_declared_classes(), $classes);
                $this->registryConsoleRun($declaredCommands, $this->execClassName);

            }catch(Exception $e){
                return;
            }
        }


    }

    public function useModuleTplNamespace()
    {
        $this->useModuleTplNS = true;
    }

    public function useModuleXfrontNamespace()
    {
        $this->useModuleXfrontNS = true;
    }


    public function setConfig($config = null)
    {
        $this->_config = $config;
    }

    public function getInstance(){

        $implements = class_implements($this);

        if (in_array('xPluginListener', $implements)) {
            $objInstance = xCore::pluginFactory($this->execClassName . '.front');
            $objInstance->setListener($this);
        }

        if (in_array('xModuleListener', $implements)) {
            if (xConfig::get('GLOBAL', 'currentMode') == 'front') {
                $objInstance = xCore::moduleFactory($this->execClassName . '.front');
            } else {
                $objInstance = xCore::loadCommonClass($this->execClassName);
            }
        }

        return $objInstance;
    }

    final public function __call($chrMethod, $arrArguments)
    {

        $objInstance=$this->getInstance();

        if (method_exists($objInstance, $chrMethod)) {
            return call_user_func_array(array(
                $objInstance,
                $chrMethod,
            ), $arrArguments);
        } else {
            X4\Classes\xRegistry::get('errorLogger')->error(' ' . $chrMethod);
        }
    }
}
