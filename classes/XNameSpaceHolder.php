<?php

namespace X4\Classes;

class XNameSpaceHolder
{
    private static $nameSpaces;
    private static $callModeles;
    private static $lastInstance;


    public static function getInstanceSource($ns, $method)
    {
        return self::$nameSpaces[$ns][$method];
    }

    /**
     * Добавить все методы объекта в неймспейс
     *
     * @param mixed $ns - имя нейспейса
     * @param mixed $object - объект
     */
    public static function addObjectToNS($ns, $object)
    {
        $className = get_class($object);


        $f = new \ReflectionClass($className);

        $parent = $f->getParentClass();
        if ($methods = $f->getMethods(\ReflectionMethod::IS_PUBLIC)) {

            foreach ($methods as $method) {
                if (($method->class == $className or $method->class == $parent->name) && !(strstr($method->name, '__'))) {
                    self::$nameSpaces[$ns][$method->name] =& $object;
                }
            }
        }

    }

    /**
     * Добавить метод в нейспейс
     *
     * @param string $ns - имя нейспейса
     * @param mixed $methods - 1 метод либо массив методов
     * @param object $object - объект обработчик
     */
    public static function addMethodsToNS($ns, $methods, $object)
    {
        if (!is_array($methods)) {
            $methods = array($methods);
        }

        if ($methods) {
            foreach ($methods as $method) {
                self::$nameSpaces[$ns][$method] =& $object;
            }
        }

    }

    /**
     * Проверяет неймспейс на существование,если указан метод то проверяется существание  объякта у определенного  метода у данного неймспейса
     *
     * @param string $ns = имя нейспейса
     * @param string $method = название метода
     */
    public static function isNameSpaceExists($ns, $method = '')
    {

        if (isset(self::$nameSpaces[$ns])) {
            if ($method && method_exists(self::$nameSpaces[$ns][$method], $method)) {
                return true;

            } elseif (self::$nameSpaces[$ns] && !$method) {
                return true;

            } else {

                return false;
            }
        }

    }

    /**
     * Добавить модель вызова
     *
     * @param mixed $name - имя модели
     * @param mixed $wakeUpFunction - lyambda функция для вызова определенной модели
     * стандартные модели вызова module,plugin,classs
     */


    public static function addCallModel($name, $wakeUpFunction)
    {
        self::$callModeles[$name] = $wakeUpFunction;

    }

    /**
     * получить последний объект с которого произошел последний вызов метода нейспейса
     *
     */
    public static function getLastInstance()
    {
        return self::$lastInstance;
    }

    /**
     * Вызвать методо из опрежделенного неймспейса
     *
     * @param string $ns - название неймспейса
     * @param string $method - метод который необходимо вызвать из определенного интерфейса
     * @param mixed $arguments - обязательно ассоциативный  масссив
     * @return mixed
     */

    public static function call($ns, $method, $arguments = null, $additionalArguments = null)
    {

        if (!method_exists(self::$nameSpaces[$ns][$method], $method)) {
            $nsExpl = explode('.', $ns);
            if ($wakeUpFunction = self::$callModeles[$nsExpl[0]]) {
                $wakeUpFunction($nsExpl);
            }
        }


        if (method_exists(self::$nameSpaces[$ns][$method], $method)) {
            self::$lastInstance = self::$nameSpaces[$ns][$method];

            $result = call_user_func_array(array(self::$nameSpaces[$ns][$method], $method), array($arguments, $additionalArguments));

            if ($result === null) {
                return true;
            } else {
                return $result;
            }

        } else {

            return null;
        }
    }

}


XNameSpaceHolder::addCallModel('plugin', function ($params) {


    $type = end($params);

    switch ($type) {

        case 'tpl':

            \xTpl::__load($params[1] . '.' . $params[2], true);

            break;

        case 'xfront':


            if ($xfrontModuleInstance = \xCore::pluginFactory($params[1] . '.' . $params[2] . '.xfront')) {
                XNameSpaceHolder::addObjectToNS('plugin.' . $params[1] . '.xfront', $xfrontModuleInstance);
            }

            break;

        case 'back':


            if ($backModuleInstance = \xCore::pluginFactory($params[1] . '.' . $params[2] . '.back')) {
                //  XNameSpaceHolder::addObjectToNS('plugin.' . $params[1] . '.back', $backModuleInstance);
            }

            break;

    }

});

// объявление стандартных моделей вызова
XNameSpaceHolder::addCallModel('module', function ($params) {

    switch ($params[2]) {

        case 'tpl':
            \xTpl::__load($params[1]);

            break;

        case 'xfront':

            if ($xfrontModuleInstance = \xCore::moduleFactory($params[1] . '.' . $params[2])) {
                XNameSpaceHolder::addObjectToNS('module.' . $params[1] . '.xfront', $xfrontModuleInstance);

                $xfrontModuleInstance->initiateXfrontPlugins();
                $xfrontModuleInstance->initiateFrontActionsCallNS();

            }

            break;

        case 'front':

            if ($frontModuleInstance = \xCore::moduleFactory($params[1] . '.' . $params[2])) {
                XNameSpaceHolder::addObjectToNS('module.' . $params[1] . '.front', $frontModuleInstance);
            }

            break;

        case 'back':


            if ($backModuleInstance = \xCore::moduleFactory($params[1] . '.' . $params[2])) {
                XNameSpaceHolder::addObjectToNS('module.' . $params[1] . '.back', $backModuleInstance);
                $backModuleInstance->initiateBackPlugins();
            }

            break;

        // deprecated
        case 'class':

            if ($classInstance = \xCore::incModuleFactory($params[1])) {

                XNameSpaceHolder::addObjectToNS('module.' . $params[1] . '.' . $params[2], $classInstance);

            }

            break;



        case 'adm':
            $className="\\X4\AdminBack\\{$params[1]}";
            $classInstance = new $className();

            if (!empty($classInstance)) {
                XNameSpaceHolder::addObjectToNS('module.' . $params[1] . '.' . $params[2], $classInstance);

            }

            break;

    }

});