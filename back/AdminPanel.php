<?php
namespace X4\AdminBack;

use X4\Classes\XNameSpaceHolder;
use X4\Classes\XRegistry;
use X4\Classes\XTreeEngine;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


class AdminPanel extends \x4class
{
    public $_moduleName = 'AdminPanel';
    public $lct;


    public function __construct()
    {
        parent::__construct();
        XNameSpaceHolder::addObjectToNS('module.AdminPanel.back', $this);

    }

    public function ping()
    {
        $this->result['ping'] = 'pong';
    }

    public function startMapping()
    {
        \XOAD_Server::allowClasses('Connector');
    }


    public function enableFrontEditor($params = null)
    {
        $_SESSION['fronted']['enabled'] = true;
    }

    public function disableFrontEditor($params = null)
    {
        $_SESSION['fronted']['enabled'] = false;
    }

    private function blockNonAuthCodedXoad()
    {

        if (!$_SESSION['authcode']) {
            if (!$_REQUEST['xoadCall']) {
                header('location: login.php');
            } else {

                echo 'SESSION_TIME_EXPIRED';
            }

            die();

        }

    }


    public function listen()
    {

        $backLogger = new Logger('backLogger');

        if (\xConfig::get('GLOBAL', 'enableBackLogs')) {
            $backLogger->pushHandler(new StreamHandler(PATH_ . 'logs/back.log', Logger::INFO));
        } else {
            $backLogger->pushHandler(new \Monolog\Handler\NullHandler());
        }

        $backLogger->info('user', array($_SESSION['user']['login']));

        xRegistry::set('backLogger', $backLogger);

        $this->blockNonAuthCodedXoad();

        if (isset($_REQUEST['xoadCall'])) {
            if (\xConfig::get('GLOBAL', 'compressXoadRequests')) {
                ob_start("ob_gzhandler");

            } else {

                ob_start();
            }

            $this->startMapping();

            if (\XOAD_Server::runServer()) {
                $all = ob_get_contents();
                ob_end_clean();
                $backLogger->info('command', $GLOBALS['_XOAD_SERVER_REQUEST_BODY']['arguments']);
                return $all;

            }
        } elseif (isset($_REQUEST['action'])) {
            return $this->dispatchAction($_REQUEST['action']);

        } else {

            $tpl = \xCore::moduleFactory('templates.back');
            return $this->buildMainInterface();
        }

    }


    public function clearCache($params)
    {

        \xCore::lockSite(503);

        $result = $this->_EVM->fire('AdminPanel:beforeCacheClear', array('instance' => $this));

        if ($result['break']) {
            return;
        }


        $foldersToClear = array('PSG-param','skuPSG');

        if ($result['foldersToClear']) {
            $foldersToClear = array_replace_recursive($foldersToClear, $result['foldersToClear']);
        }

        \XFILES::unlinkRecursive(PATH_ . 'cache/', false, true, $foldersToClear);


        $this->_EVM->fire('AdminPanel:afterCacheClear', array('instance' => $this));


        $this->clearCacheConsole($params);


        $this->result['result'] = true;

        \xCore::unlockSite();
    }


    public function clearCacheConsole($options)
    {

        if ($options['setTreeBoost']) {

            $result = exec('php console.php catalog:boostTree > /dev/null &');

        }

        if ($options['recalculatePSG']) {

            $result = exec('php console.php catalog:reindexPSG > /dev/null &');
            xRegistry::get('backLogger')->info('ready');

        }

    }

    public function getLangForModule($params)
    {
        $this->result['lang'] = \Common::getModuleLang($params['module'], $_SESSION['lang']);
    }



    public function initial($params = null)
    {
        $this->result['data'] = $this->getAdminPanelData();

    }

    public function loadModuleTplsBack($params)
    {

        $this->loadModuleTpls($params['module'], $params['tpls']);

    }

    private function tplKeyTransform($keys)
    {
        $keyr = array();
        if ($keys) {
            foreach ($keys as $key) {
                $keyr[] = '{' . $key . '}';
            }
            return $keyr;
        }
    }

    public function tplLangConvert($lang, $file, $moduleName = null)
    {
        if (!$lang && $moduleName) {
            $lang = \Common::getModuleLang($moduleName, $_SESSION['lang']);
        }
        return str_replace($this->tplKeyTransform(array_keys($lang)), $lang, file_get_contents($file));
    }

    public function loadModuleTpls($moduleName, $tplNames, $_return = false)
    {

        if (is_array($tplNames)) {
            foreach ($tplNames as $tpl) {
                $modifier = '';
                if ((strpos($tpl['tplName'], '@')) !== false) {
                    $tplExploded = explode('@', $tpl['tplName']);
                    $tplLoadName = $tplExploded[0];
                    $modifier = $tplExploded[1];
                } else {
                    $tplLoadName = $tpl['tplName'];
                }

                $moduleNamePartial = explode('.', $moduleName);

                if ($moduleName == 'AdminPanel') {
                    $file = \Common::getAdminTpl($tplLoadName . '.html');

                } elseif (isset($moduleNamePartial[1])) {
                    //plugin template detected     
                    $file = \Common::getPluginTpl($moduleName, $tplLoadName . '.html');

                } else {
                    $file = \Common::getModuleTpl($moduleName, $tplLoadName . '.html');
                }

                $fstats = stat($file);
                if (isset($tpl['time'])) {
                    if ($tpl['time'] != $fstats['mtime']) {
                        if (is_array($lang = \Common::getModuleLang($moduleName, $_SESSION['lang']))) {
                            $this->lct['templates'][$tpl['tplName']] = $this->tplLangConvert($lang, $file);
                        } else {
                            $this->lct['templates'][$tpl['tplName']] = file_get_contents($file);
                        }
                    }
                } else {

                    if (is_array($lang = \Common::getModuleLang($moduleName, $_SESSION['lang']))) {
                        if (!$_return) {
                            $this->lct['templates'][$tpl['tplName']] = $this->tplLangConvert($lang, $file);
                        } else {
                            return $this->tplLangConvert($lang, $file);
                        }
                    } else {
                        $this->lct['templates'][$tpl['tplName']] = file_get_contents($file);
                    }
                }
                $this->_TMS->addFileSection($this->lct['templates'][$tpl['tplName']], true);
                if ($this->_TMS->isSectionDefined($tplLoadName)) {
                    $this->_TMS->addReplace($tplLoadName, 'action', $modifier);
                    $this->lct['templates'][$tpl['tplName']] = $this->_TMS->parseSection($tplLoadName);
                }
                $this->lct['timers'][$tpl['tplName']] = $fstats['mtime'];
            }
            return true;
        }
    }


    private function applyModuleAccess($modules)
    {
        foreach ($modules as $module => &$instance) {
            if (!$_SESSION['user']['moduleAccess'][$module]) {
                unset($modules[$module]);
            }
        }

        return $modules;

    }

    public function getAdminPanelData()
    {
        static $apd = array();
        if (!$apd) {
            $apd['version'] = \xCore::getVersion();
            $apd['charset'] = \xConfig::get('GLOBAL', 'siteEncoding');
            $apd['license'] = \xCore::getLicense();
            $apd['xConnector'] = \XOAD_Client::register(new \Connector());
            $apd['__uid'] = $GLOBALS['__uid'];
            $apd['lang'] = $_SESSION['lang'];
            $apd['login'] = $_SESSION['user']['login'];
            $apd['siteByDefault'] = HOST;
            $apd['userType'] = $_SESSION['user']['type'];


            if ($_SESSION['user']['type'] == '_SUPERADMIN') {
                $apd['modulesList'] = \xCore::discoverModules();

            } else {

                $apd['modulesList'] = $this->applyModuleAccess(\xCore::discoverModules());

            }


            $apd['XJS'] = \xConfig::get('WEBPATH', 'XJS');
            $apd['components'] = str_replace(HOST, '', \xConfig::get('WEBPATH', 'XJS')) . '_components/';
        }
        return $apd;
    }

    public function showLogin($e = null)
    {
        $this->_TMS->addFileSection($this->loadModuleTpls($this->_moduleName, array(
            array(
                'tplName' => 'login'
            )
        ), true), true);
        if ($e) {
            $this->_TMS->parseSection('error', true);
        }
        $this->_TMS->addMassReplace('main', $this->getAdminPanelData());
        return $this->_TMS->parseSection('main');
    }

    public function buildMainInterface($pageFields = null)
    {
        $this->_TMS->addFileSection($this->loadModuleTpls($this->_moduleName, array(array('tplName' => 'run')), true), true);
        $this->_TMS->addMassReplace('main', $this->getAdminPanelData());
        return $this->_TMS->parseSection('main');
    }

    public function dispatchAction($action)
    {
        switch ($action) {

            case 'download':
                $MFM = new MatrixFileManager();
                $MFM->downloadFile();

                break;

            case 'getfile':
                $MFM = new MatrixFileManager();
                $MFM->getFile();
                break;

        }
    }


    public function getSessionId()
    {
        $this->result['sessionid'] = session_id();
    }
}
