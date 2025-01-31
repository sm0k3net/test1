<?php

use X4\Classes\MultiSection;
use X4\Classes\MultiSectionHL;
use X4\Classes\XCache;
use X4\Classes\XNameSpaceHolder;
use X4\Classes\XRegistry;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Uri;


/**
 * Родительский класс  для front составляющей модулей.
 */
class xModule
    extends xModulePrototype
{
    public $_meta;
    public $_requestAction;
    public $tmsStack = array();
    public $requestAction;

    public function __construct($className)
    {
        parent::__construct($className);
        $this->tmsStack['default'] = $this->_TMS;
    }


    public function getLink($pageId = null)
    {
        $pages = xCore::loadCommonClass('pages');

        if (!$pageId) {
            $pageId = XRegistry::get('TPA')->currentPageNode['id'];
        }

        return $pages->createPagePath($pageId);
    }

    public function dispatchFrontAction($action, $actionParams)
    {

        //! не кешируются такие действия
        if (XNameSpaceHolder::isNameSpaceExists('module.' . $this->_moduleName . '.front', $action)) {
            return XNameSpaceHolder::call('module.' . $this->_moduleName . '.front', $action, $actionParams);
        }

        if (!empty($actionParams['fullActionData']['mainServerAction'])) {
            $serverAction = $actionParams['fullActionData']['mainServerAction'];
            $callFunc = $action;
        } else {
            $serverAction = $action;
            $callFunc = 'run';

        }

        if (!isset($this->actionInstances[$serverAction])) {
            $this->actionInstances[$serverAction] = $this->initiateFrontActionInstance($serverAction);
        }

        $actionInstance = $this->actionInstances[$serverAction]->newInstance($this->_moduleName);

        $actionParams['params']['dispatchedAction'] = $action;

        $actionParams['cache'] = $cache = $this->readActionCache($actionParams, $actionInstance);

        if (!empty($cache['callResult'])) {
            if (method_exists($actionInstance, 'onCacheRead')) {
                return $actionInstance->onCacheRead($actionParams);

            } else {
                return $cache['callResult'];
            }

        }

        //$actionParams['requestObject'] = new ServerRequest('GET', new Uri('/'));

        if (method_exists($actionInstance, 'build')) {
            $actionResult = $actionInstance->build($actionParams);
        }


        if (method_exists($actionInstance, $callFunc . 'Headless') && XRegistry::get('TPA')->getRenderMode() == 'HEADLESS') {
            $callFunc = $callFunc . 'Headless';
        }

        $actionResult = $actionInstance->$callFunc($actionParams);

        $this->writeActionCache($actionParams, $actionResult, $actionInstance->getDataCache(false));

        unset($actionInstance);

        return $actionResult;
    }

    public function initiateFrontActionInstance($action)
    {
        require_once xConfig::get('PATH', 'MODULES') . $this->_moduleName . '/actions/' . $action . '.action.php';
        $instance = new ReflectionClass($action . 'Action');
        $instance->getConstructor();
        return $instance;
    }

    private function readActionCache($actionData, $actionInstance)
    {
        if (xConfig::get('GLOBAL', 'actionModuleCache') && $actionData['base']['_Cache']) {

            if ($actionData['base']['_cacheLevel'] == 'dynamic' or !($actionData['base']['_cacheLevel'])) {
                $actionData['revid'] = $actionData['base']['moduleId'] . xConfig::get('PATH', 'baseUrl');
            } elseif ($actionData['base']['_cacheLevel'] == 'static') {
                $actionData['revid'] = $actionData['base']['moduleId'];
                unset($actionData['request']);
            }

            unset($actionData['request']['requestActionSub']);

            if (isset($actionData['fullActionData'])) {
                unset($actionData['fullActionData']);
            }

            if (isset($actionData['request']['requestData']['PHPSESSID'])) {
                unset($actionData['request']['requestData']['PHPSESSID']);
            }

            $actionKey = Common::createMark($_SESSION['siteuser']['currency']['id'], $actionData);

            $cacheSavePath = $this->_moduleName . '/' . $actionData['base']['_Action'] . '-' . $actionData['base']['moduleId'];

            $cache = XCache::serializedRead($cacheSavePath, $actionKey);

            $cache = array(
                'cacheData' => $cache['cacheData'],
                'callResult' => $cache['callResult'],
                'actionKey' => $actionKey,
                'cacheSavePath' => $cacheSavePath,
            );

            if ($cacheTemp = XRegistry::get('EVM')->fire($this->_moduleName . '.onModuleCacheRead', array('instance' => $actionInstance, 'actionData' => $actionData, 'cache' => $cache))) {
                $cache = $cacheTemp;
            }

            return $cache;

        }
    }

    private function writeActionCache($actionData, $callResult, $cacheData = null)
    {

        if (xConfig::get('GLOBAL', 'actionModuleCache') && $actionData['base']['_Cache']) {
            $cache = array(
                'page' => xConfig::get('PATH', 'baseUrl'),
                'callResult' => $callResult,
                'cacheData' => $cacheData
            );

            if ($cacheTemp = XRegistry::get('EVM')->fire($this->_moduleName . '.onModuleCacheWrite', array('instance' => $this, 'actionData' => $actionData, 'cache' => $cache))) {
                $cache = $cacheTemp;
            }

            XCache::serializedWrite($cache, $actionData['cache']['cacheSavePath'], $actionData['cache']['actionKey']);
        }

    }

    public function requestActionSet($action)
    {
        if ($action) {
            foreach ($this->_commonObj->_frontActionList as $iaction) {
                if (isset($iaction['serverActions']) && (is_array($iaction['serverActions']))) {
                    if (in_array($action, $iaction['serverActions'])) {
                        $this->requestAction = $action;

                        return;
                    }
                }
            }
        }
    }

    public function initiateXfrontPlugins()
    {
        if ($mPlugins = xCore::getModulePluginsListeners($this->_moduleName)) {
            foreach ($mPlugins as $mPlugName => $mPlug) {
                if ($mPlug->useModuleXfrontNS) {
                    if ($pluginXfront = xCore::pluginFactory($this->_moduleName . '.' . $mPlugName . '.xfront')) {
                        $pluginXfront->setInstance($this);
                        XNameSpaceHolder::addObjectToNS('module.' . $this->_moduleName . '.xfront', $pluginXfront);
                    }
                }
            }
        }
    }

    public function getFrontActionInstance($actionName)
    {
        return $this->actionInstances[$actionName];
    }

    public function initiateFrontActionsCallNS()
    {
        if ($actions = xCore::moduleFrontActionsList($this->_moduleName)) {
            foreach ($actions as $actionName => $actionFile) {
                if (!isset($this->actionInstances[$actionName])) {
                    $this->actionInstances[$actionName] = $this->initiateFrontActionInstance($actionName);
                }

                $this->actionInstances[$actionName]->newInstance($this->_moduleName);
            }
        }
    }

    public function createPageDestination($destinationPageId, $excludeHost = false, $action = false)
    {
        static $destinationCache;

        if (isset($destinationCache[$destinationPageId])) {
            return $destinationCache[$destinationPageId];
        }

        $pages = xCore::loadCommonClass('pages');

        if ($destinationPageId) {
            return $destinationCache[$destinationPageId] = $pages->createPagePath($destinationPageId, $excludeHost, $action);
        }
    }


    private function callActionLogic($context, $action, $actionDataStructured)
    {

        if (method_exists($context, $action)) {

            $callResult = call_user_func_array(array($context, $action), array($actionDataStructured));

        } elseif (XNameSpaceHolder::isNameSpaceExists('module.' . $this->_moduleName . '.front', $action)) {

            $callResult = XNameSpaceHolder::call('module.' . $this->_moduleName . '.front', $action, $actionDataStructured);

        } else {

            $callResult = call_user_func_array(array($context, 'dispatchFrontAction'), array($action, $actionDataStructured));
        }

        return $callResult;
    }

    public function execute($actionData, $moduleId)
    {

        if (isset($actionData) && $actionData = $this->isAction($actionData)) {

            $actionData['id'] = $moduleId;

            $this->pushtmsStack($actionData['_Type'] . '-' . $moduleId);

            $context = $actionData['fullActionData']['callContext'];

            if (empty($context)) {
                $context = $this;
            }

            $action = $actionData['_Action'];

            $actionDataStructured = $this->actionDataToStructured($actionData);

            $actionResultEvent = XRegistry::get('EVM')->fire($this->_moduleName . '.front.onBeforeActionCall', array('context' => $context, 'actionDataStructured' =>

                $actionDataStructured, 'action' => $action));

            if (!$actionResultEvent['stopExecution']) {

                if (!empty($actionResultEvent['action'])) {
                    $actionDataStructured = array_replace_recursive($actionDataStructured, $actionResultEvent['action']);
                }

                $callResult = $this->callActionLogic($context, $action, $actionDataStructured);

                $this->reversetmsStack();

            }

            if (isset($callResult)) {
                return $callResult;
            }
        }
    }

    public function isAction($actionData)
    {
        $actionData['fullActionData'] = $this->_commonObj->getAction($action = $actionData['_Action']);

        $server = $this->_commonObj->getServerActions($action);

        if (isset($server) && $this->requestAction && in_array($this->requestAction, $server)) {
            $actionData['_Action'] = $this->requestAction;
            $actionData['fullActionData']['mainServerAction'] = $action;
        } elseif (!$this->requestAction && isset($actionData['_DefaultAction'])) {
            $actionData['_Action'] = $actionData['_DefaultAction'];
        }

        $actionData['request'] = XRegistry::get('TPA')->getRequestActionInfo();

        return $actionData;
    }

    public function pushtmsStack($key)
    {
        if (!isset($this->tmsStack[$key])) {

            if ('HEADLESS' != XRegistry::get('TPA')->getRenderMode()) {
                $this->_TMS = $this->tmsStack[$key] = new MultiSection();
            }


        } else {
            $this->_TMS = $this->tmsStack[$key];
        }

        $this->tmsStackHistory[] = $key;
    }

    private function actionDataToStructured($actionData)
    {
        $actionDataStructured = array();

        foreach ($actionData as $actKey => $act) {
            if (strstr($actKey, '__secondary.')) {
                $actKey = explode('.', $actKey);
                $actionDataStructured['secondary'][$actKey[1]] = $act;
            } elseif ($actKey[0] == '_') {
                $actionDataStructured['base'][$actKey] = $act;
            } elseif (is_array($act)) {
                $actionDataStructured[$actKey] = $act;
            } else {
                $actionDataStructured['params'][$actKey] = $act;
            }
        }

        $actionDataStructured['base']['moduleId'] = $actionData['id'];

        return $actionDataStructured;

    }

    public function reversetmsStack()
    {
        array_pop($this->tmsStackHistory);
        $current = end($this->tmsStackHistory);

        $this->_TMS = $this->tmsStack[$current];
    }
}
