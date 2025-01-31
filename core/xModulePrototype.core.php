<?php

use X4\Classes\XEventMachine;
use X4\Classes\XRegistry;


abstract class x4class
{
    public $_TMS;
    public $_EVM;
    public $_PDO;

    public function __construct()
    {
        $this->_TMS = XRegistry::get('TMS');
        $this->_PDO = XRegistry::get('XPDO');
        $this->_EVM = XEventMachine::getInstance();
    }
}

class xModulePrototype
    extends x4class
{
    public $_moduleName;
    public $_tree;
    public $_commonObj;

    public function __construct($className)
    {
        parent::__construct();

        if (preg_match('/[A-Z]/', $className, $matches, PREG_OFFSET_CAPTURE)) {
            $this->_moduleType = substr($className, $matches[0][1]);
            $this->_moduleName = substr($className, 0, $matches[0][1]);
        }

        $this->_config = xConfig::get('MODULES', $this->_moduleName);

        $this->_commonObj = call_user_func($this->_moduleName . 'Common::getInstance', $this->_moduleName . 'Common');

        if ($this->_commonObj->_tree) {
            $this->_tree = $this->_commonObj->_tree;
        }
    }

    public function loadModuleTemplate($tpl, $treatAs = null, $tplFolder = null)
    {
        if ($treatAs) {
            $_moduleType = $treatAs;
        } else {
            $_moduleType = $this->_moduleType;
        }


        if (!isset($tpl)) {
            throw new Exception($_moduleType . ': module-template-is-empty');
        }

        switch ($_moduleType) {
            case 'Front':
                $tplFolder = $domain['params']['TemplateFolder'] = 'opsio.bi';
                if (empty($tplFolder)) {
                    $domain = xConfig::get('GLOBAL', 'domain');
                    $tplFolder = $domain['params']['TemplateFolder'];
                }

                $this->_TMS->addFileSection(Common::getFrontModuleTplPath($this->_moduleName, $tpl, $tplFolder));

                break;

            case 'Back':
                $tpl = str_replace('.html', '', $tpl);

                $this->_TMS->addFileSection(
                    XRegistry::get('ADM')->loadModuleTpls($this->_moduleName, array(array('tplName' => $tpl)), true),
                    true);
        }
    }
}
