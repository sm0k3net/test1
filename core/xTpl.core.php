<?php
use X4\Classes\XNameSpaceHolder;
use X4\Classes\XRegistry;
use X4\Classes\MultiSection;
use X4\Classes\XCache;

class xTpl
{
    private $execClassName;
    private static $xTplInstance;
    private $objModuleInstance;

    public function __construct($name)
    {
        $this->execClassName = $name;

        if (!$this->objModuleInstance) {
            $implements = class_implements($this);

            if (in_array('xPluginTpl', $implements)) {
                $this->objModuleInstance = xCore::pluginFactory($this->execClassName.'.front');
            } else {
                $this->objModuleInstance = xCore::moduleFactory($this->execClassName.'.front');
            }
        }
    }

    public static function __load($module, $addModuleNamespace = false)
    {
        if (isset(self::$xTplInstance[$module])) {
            return self::$xTplInstance[$module];
        }

        if (strpos($module, '.') !== false) {
            $plugin = explode('.', $module);

            if (file_exists(
                $tplClass = xConfig::get('PATH', 'PLUGINS').$module.'/'.$plugin[1].'.'.'tpl.class.php')) {
                include_once $tplClass;
                $tplClassName = $plugin[1].'Tpl';

                self::$xTplInstance[$module] = new $tplClassName($plugin[1]);

                if ($addModuleNamespace) {
                    XNameSpaceHolder::addObjectToNS('module.'.$plugin[0].'.tpl', self::$xTplInstance[$module]);
                }
                  
                XNameSpaceHolder::addObjectToNS('plugin.'.$module.'.tpl', self::$xTplInstance[$module]);

                return self::$xTplInstance[$module];
            }
        } else {
            if (file_exists(
                $tplClass = xConfig::get('PATH', 'MODULES').$module.'/'.$module.'.'.'tpl.class.php')) {
					
					
				xCore::loadModuleConfig($module);				
				$config=xConfig::get('MODULES', $module);				
				if($config['disable']) return;
				
                include_once $tplClass;
                $tplClassName = $module.'Tpl';
                self::$xTplInstance[$module] = new $tplClassName($module);
                XNameSpaceHolder::addObjectToNS('module.'.$module.'.tpl', self::$xTplInstance[$module]);
                // стартуем tpl нейспейсы плагинов данного модуля

				
				
                if ($mPlugins = xCore::getModulePluginsListeners($module)) {
                    foreach ($mPlugins as $mPlugName => $mPlug) {
                        if ($mPlug->useModuleTplNS) {
                            self::__load($module.'.'.$mPlugName, true);
                        }
                    }
                }
                return self::$xTplInstance[$module];
            }
        }
    }

    public function __get($property)
    {
        if (property_exists($this->objModuleInstance, $property)) {
            return $this->objModuleInstance->$property;
        }
    }

    public function __set($property, $value)
    {
        if (property_exists($this->objModuleInstance, $property)) {
            $this->objModuleInstance->$property = $value;
        }
    }

    final public function __call($chrMethod, $arrArguments)
    {
        return call_user_func_array(array(
            $this->objModuleInstance,
            $chrMethod,
        ), $arrArguments);
    }
}
