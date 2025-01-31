<?php

use X4\Classes\XTreeEngine;
use X4\Classes\XRegistry;
use X4\Classes\XNameSpaceHolder;

class xModuleBack
    extends xModulePrototype
{
    public function __construct($className)
    {
        parent::__construct($className);
    }

    public function initiateBackPlugins()
    {
        if ($mPlugins = xCore::getModulePlugins($this->_moduleName)) {
            foreach ($mPlugins as $mPlugName => $mPlug) {
                Common::getModuleLang($this->_moduleName . '.' . $mPlugName, $_SESSION['lang']);
                $pluginBack = xCore::pluginFactory($this->_moduleName . '.' . $mPlugName . '.back');
                XNameSpaceHolder::addObjectToNS('module.' . $this->_moduleName . '.back', $pluginBack);
            }
        }
    }


    public function loadTemplates()
    {
    }

    /**
     * Отправить ошибку на front-end в growler.
     *
     * @param mixed $msg
     */
    public function pushError($msg, $translateSection = 'common')
    {
        if (!$msgTranslated = $this->_commonObj->translateWord($msg)) {
            $msgTranslated = $msg;
        }

        connector::pushError($msgTranslated, $this->_moduleName);
    }

    /**
     * Отправить сообщение на front-end в growler.
     *
     * @param mixed $msg
     */
    public function pushMessage($msg, $translateSection = 'common')
    {
        if (!$msgTranslated = $this->_commonObj->translateWord($msg)) {
            $msgTranslated = $msg;
        }

        connector::pushMessage($msgTranslated, $this->_moduleName);
    }

    /**
     * Получить все действия для данного модуля для back  ajax запроса
     * $params['module']  - модуль для которого необходимо полуичть действия.
     */
    public function getModuleActions($params)
    {
        if ($actions = $this->_commonObj->getActions()) {

            foreach ($actions as $name => $action) {
                unset($actions[$name]['callContext']);
            }

            $this->result['moduleActions'] = $actions;
        }
    }

    public function copyObj($params, $tree = null)
    {
        if (!$tree) {
            $tree = $this->_tree;
        }

        foreach ($params['id'] as $id) {
            $newToOld = $tree->copyNodes($params['ancestor'], (int)$id);
            XRegistry::get('EVM')->fire('module.' . $this->_moduleName . '.back:afterCopyObj', array(
                'newToOld' => $newToOld,
                'tree' => $tree,
            ));
        }
    }

    public function deleteObj($params, $tree = null, $logAllDeleted = false)
    {
        if (!$tree) {
            $tree = $this->_tree;
        }
        $log = array();
        if (!is_array($params['id'])) {
            $params['id'] = array($params['id']);
        }

        foreach ($params['id'] as $id) {
            if ($logAllDeleted) {
                if ($qlog = $tree->selectStruct(array('id'))->childs($id)->format('keyval', 'id', 'id')->run()) {
                    $log = $qlog + $log;
                }
            }

            $tree->delete()->childs($id)->run();

            if ($tree->delete()->where(array('@id', '=', $id))->run()) {
                $deleted[] = $id;

                if ($logAllDeleted) {
                    $log[$id] = $id;
                }
            }
        }

        if ($logAllDeleted) {
            $deleted = $log;
        }

        return $this->result['deletedList'] = $deleted;
    }

    public function enableCatObject($params, $tree)
    {
        if (!$tree) {
            $tree = $this->_tree;
        }

        if (is_array($params['id'])) {
            foreach ($params['id'] as $sid) {
                $tree->setStructData($sid, 'disabled', 0);
            }

            return $this->result['enabledList'] = $params['id'];
        }
    }

    public function disableCatObject($params, $tree)
    {
        if (!$tree) {
            $tree = $this->_tree;
        }

        if (is_array($params['id'])) {
            foreach ($params['id'] as $sid) {
                $tree->setStructData($sid, 'disabled', 1);
            }

            return $this->result['disabledList'] = $params['id'];
        }
    }

    public function consoleIt($params)
    {
        $this->result['console'] = $this->_tree->getNodeInfo($params['id']);
    }

    public function getActionProperties($params)
    {
        $actionData = $this->_commonObj->getAction($params['action']);
        $action = 'onAction_' . $params['action'];

        if (isset($actionData['callContext'])) {
            $pluginContext = xCore::pluginFactory($actionData['callContext']->execClassName . '.back');
            $pluginContext->loadTemplate('ainterface');
        } else {
            $this->loadModuleTemplate('ainterface');
        }

        if (XNameSpaceHolder::isNameSpaceExists('module.' . $this->_moduleName . '.back', $action)) {
            XNameSpaceHolder::call('module.' . $this->_moduleName . '.back', $action, $params);
        } else {

            //throw  error 
        }

        $this->result['tpl'] = $this->_TMS->parseSection($params['action']);
    }

    public function changeAncestorGrid($params)
    {
        if (is_array($params['id'])) {
            $ex = $params['id'];
        } elseif (strpos($params['id'], ',') !== false) {
            $ex = explode(',', $params['id']);
        }

        if (is_array($ex)) {
            foreach ($ex as $e) {
                $params['id'] = $e;
                $this->changeAncestorGridProcess($params);
            }
        } else {
            $this->changeAncestorGridProcess($params);
        }
    }

    public function changeAncestorGridProcess($params)
    {
        if ($params['tree']) {
            $tree = $params['tree'];
        } else {
            $tree = $this->_tree;
        }

        $params['id'] = (int)$params['id'];
        $params['ancestor'] = (int)$params['ancestor'];
        //только смена позиции без сменвы предка          
        if (($params['relative'] == 'sibling') && (!$params['ancestorChanged'])) {
            $tree->moveRate($params['id'], $params['pointNode'], 'down');
            $this->result['dragOK'] = true;

            // смена позиции и смена предка            
        } elseif (($params['relative'] == 'sibling') && ($params['ancestorChanged'])) {
            try {
                $tree->changeAncestor($params['id'], $params['ancestor']);
            } catch (Exception $e) {
                $this->result['dragOK'] = false;
            }

            $tree->moveRate($params['id'], $params['pointNode']);
            $this->result['dragOK'] = true;

            //только смена ппредка  (установка в последнюю позициию)          
        } elseif (($params['relative'] == 'child')) {
            try {
                $tree->changeAncestor($params['id'], $params['ancestor']);
                $this->result['dragOK'] = true;
            } catch (Exception $e) {
                $this->result['dragOK'] = false;
            }
        } else {
            $this->result['dragOK'] = false;
        }
    }

    public function getTemplateAlias($tpl)
    {
        if ($file = fopen($tpl, 'r')) {
            $line = fgets($file);
            fclose($file);

            if ($line[0] == '@') {
                return substr($line, 1);
            }
        }
    }

    public function getPermission($params)
    {
        $this->result[$params['permission']] = (bool)$this->_commonObj->getPermission($params['permission']);
    }


    public function getTemplatesList($actions = '', $useRealPath = false, $recursive = false,
                                     $getInnerModuleTemplates = false, $clearAlias = false, $moduleName = '')
    {

        if (!$actions) {
            $ext = array('.html');
        } else {
            if (!is_array($actions)) {
                $actions = array($actions);
            }

            foreach ($actions as $action) {
                $ext[] = '.' . $action . '.html';
            }
        }

        if ($recursive) {
            $type = 'all';
        } else {
            $type = 'files';
        }

        if ($getInnerModuleTemplates) {
            $prefixPath = xConfig::get('PATH', 'MODULES');
            $postfix = '/tpl/';
        } else {
            $branches = Common::getTemplateBranches();
            $prefixPath = array();

            foreach ($branches as $branch) {
                $prefixPath[$branch] = xConfig::get('PATH', 'TEMPLATES') . $branch . '/_modules/'.$this->_moduleName.'/';
            }

        }


        if (is_array($prefixPath)) {
            foreach ($prefixPath  as $branch =>$pathTpl) {
                $allTemplates = XFILES::filesList($pathTpl, $type, $ext, $recursive);
                if (!empty($allTemplates)) {
                    foreach ($allTemplates  as  $tpl) {

                        $reTpl = str_replace($pathTpl . '/', '', $tpl);

                        if ($alias = self::getTemplateAlias($tpl)) {
                            if (!$clearAlias) {
                                $alias = $reTpl . '(' . trim($alias) . ')';
                            }
                        }

                        if (!$useRealPath && !$recursive) {
                            $path = basename($tpl);
                        } else {
                            $path = $tpl;
                        }

                        if ($recursive) {
                            $path = str_replace($pathTpl . '/', '', $path);
                            $group = explode('/', $path);

                            if ($group[0] && $group[1]) {
                                $tpls[$branch . '-' . $group[0]][$path] = $alias;
                            }

                        } else {
                            $tpls[$path] = $alias;
                        }
                    }
                }
            }

            return $tpls;

        } else {

            foreach ($prefixPath as $pathTpl) {
                $allTemplates = XFILES::filesList($pathTpl, $type, $ext, $recursive);
                if (!empty($allTemplates)) {
                    foreach ($allTemplates as $branch => $tpl) {

                        $reTpl = str_replace($pathTpl . '/', '', $tpl);

                        if ($alias = self::getTemplateAlias($tpl)) {
                            if (!$clearAlias) {
                                $alias = $reTpl . '(' . trim($alias) . ')';
                            }
                        }

                        if (!$useRealPath && !$recursive) {
                            $path = basename($tpl);
                        } else {
                            $path = $tpl;
                        }

                        if ($recursive) {
                            $path = str_replace($pathTpl . '/', '', $path);
                            $group = explode('/', $path);

                            if ($group[0] && $group[1]) {
                                $tpls[$group[0]][$path] = $alias;
                            }

                        } else {
                            $tpls[$path] = $alias;
                        }
                    }
                }
            }


            return $tpls;

        }
    }
}
