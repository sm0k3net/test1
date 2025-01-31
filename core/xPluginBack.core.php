<?php

use X4\Classes\XRegistry;

class xPluginBack
    extends xSingleton
{
    public $_module;
    public $_pluginName;
    public $_listener;
    public $_config;

    public function __construct($pluginName, $moduleInstance)
    {
        
        
        $this->_TMS = XRegistry::get('TMS');
        $this->_PDO = XRegistry::get('XPDO');
        $this->_EVM = XRegistry::get('EVM');
        $this->_module = $moduleInstance;
        $this->_pluginName = $pluginName;
        $this->_listener = xCore::getModulePluginsListeners($this->_module->_moduleName, $this->_pluginName);
        $this->_config = xCore::loadPluginConfig($this->_module->_moduleName, $this->_pluginName);
    }
    
    public function  setConfig($config=null)
    {
       $this->_config=$config;
    }
    
    
    
     public function pushError($msg, $translateSection = 'common')
    {
        if (!$msgTranslated = $this->translateWord($msg)) {
            $msgTranslated = $msg;
        }

        connector::pushError($msgTranslated, $this->_pluginName);
    }

    /**
     * Отправить сообщение на front-end в growler.
     *
     * @param mixed $msg
     */
    public function pushMessage($msg, $translateSection = 'common')
    {
        if (!$msgTranslated = $this->translateWord($msg)) {
            $msgTranslated = $msg;
        }

        connector::pushMessage($msgTranslated, $this->_pluginName);
    }

    
    public function loadTemplate($tpl)
    {
        $tpl = str_replace('.html', '', $tpl);
        $this->_TMS->addFileSection(
            XRegistry::get('ADM')->loadModuleTpls($this->_module->_moduleName.'.'.$this->_pluginName,
                array(array('tplName' => $tpl)),
                true),
            true);
    }
    
    public function translateWord($word)
    {
        if (isset($_SESSION['lang'])) {
            $l = Common::getModuleLang($this->_pluginName, $_SESSION['lang']);

            return $l[$word] ? $l[$word] : $word;
        }

        return $word;
    }
}
