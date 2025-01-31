<?php

use X4\Classes\MultiSection;
use X4\Classes\XRegistry;
use X4\Classes\XNameSpaceHolder;
use X4\Classes\ImageRenderer;



class xModuleCommon
    extends xSingleton
{
    public $_moduleName;
    public $_tree;
    public $_frontActionList;
    public $_useTree = false;
    public $_config;
     
    public function moduleCoreRegister(){}
    public function seoConfirm(){}
    
    public function __construct($className)
    {
        $this->_moduleName = str_replace('Common', '', $className);
        $this->_config = xConfig::get('MODULES', $this->_moduleName);

        if ($this->_useTree) {
            $this->setTree($this->_moduleName);
        }

        if (method_exists($this, 'defineFrontActions')) {
            $this->defineFrontActions();
        }
    }

    /*
     * @descr Определяет действие, которое возможно вызвать из front класса
     * @param string $action название действия(только латиница)
     * @param array  $data - cодержит нижеследующие ключи:
     * @param string frontName алиас действия для отображения в списке действий модуля
     * @param array  subActions добавление данного параметра превращает действие в действие сервер и дает возможность выполнять субдействия
     * @param object  callContext контекст вызова для данного действия если вызов идет не напрямую из класса модуля
     */

    public function defineAction($action, $data = array())
    {
        if (!$frontName = $this->translateWord($action)) {
            $frontName = $action;
        }

        if (isset($data['callContext'])) {
            $callContext = $data['callContext'];
        } else {
            $callContext = null;
        }

        $this->_frontActionList[$action] = array(
            'frontName' => $frontName,
            'serverActions' => isset($data['serverActions']) ? $data['serverActions'] : null,
            'callContext' => $callContext,
            'priority' => isset($data['priority']) ? $data['priority'] : null,

        );
    }

    /*
     * @descr add action to server
     */

    public function addServerAction($serverAction, $action)
    {
        if (!$this->getServerActionExist($serverAction, $action)) {
            $this->_frontActionList[$serverAction]['serverActions'][] = $action;
        }

    }

     /*
      * @descr get user permissions list
      */

    public function getPermission($permission)
    {

        if ($_SESSION['user']['type'] == '_SUPERADMIN') {
            return true;
        }

        if (!empty($_SESSION['user']['moduleAccess']['__' . $this->_moduleName][$permission])) {
            return true;
        }
    }

    public function getAction($action)
    {
        return $this->_frontActionList[$action];
    }

    /*
    * @descr get all actions
    */

    public function getActions()
    {
        return $this->_frontActionList;
    }

    /*
    * @descr get all subactions for a given module
    * @param string  action
    */

    public function getServerActions($action)
    {
        return $this->_frontActionList[$action]['serverActions'];
    }

    public function getServerActionsFull($action)
    {
        $actions = $this->getServerActions($action);

        if (isset($actions)) {
            $allActions = $this->getActions();

            foreach ($actions as $action) {
                $extActions[$action] = $allActions[$action]['frontName'];
            }

            return $extActions;
        }
    }

    public function getServerActionExist($serverAction, $requestAction)
    {
        if (in_array($requestAction, $this->_frontActionList[$serverAction]['serverActions'])) {
            return true;
        }
    }

    /**
     * @descr get all non server actions
     */
    public function getNonServerActions()
    {
        if ($this->_frontActionList) {
            foreach ($this->_frontActionList as $key => $iAction) {
                if (!is_array($iAction['serverActions'])) {
                    $nsa[$key] = $iAction;
                }
            }

            return $nsa;
        }
    }

    /**
     * @descr setup main tree for a module
     *
     * @param string $treeName
     */
    public function setTree()
    {
        $this->_tree = new X4\Classes\XTreeEngine($this->_moduleName . '_container', X4\Classes\XRegistry::get('XPDO'));
    }


    public function translateWord($word)
    {
        if (isset($_SESSION['lang'])) {
            $l = Common::getModuleLang($this->_moduleName, $_SESSION['lang']);
            return $l[$word] ? $l[$word] : $word;
        }

        return $word;
    }

    public function getTranslation($template = 'common')
    {
        return Common::getModuleLang($this->_moduleName,
            $_SESSION['lang'],
            $template);
    }
}
