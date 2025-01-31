<?php
namespace X4\Classes;

class XRegistry
{
    protected static $store = array();

    protected function __construct()
    {
    }

    /**
     * Проверяет существуют ли данные по ключу
     *
     * @param string $name
     * @return bool
     */
    public static function exists($name)
    {
        $s = self::$store;
        return isset(self::$store[$name]);
    }

    /**
     * Возвращает данные по ключу или null, если не данных нет
     * @param string $name
     * @return unknown
     */
    public static function get($name)
    {
        return (isset(self::$store[$name])) ? self::$store[$name] : null;
    }

    /**
     * Сохраняет данные по ключу в статическом хранилище
     *
     * @param string or object with static property name $name
     * @param unknown $obj
     * @return unknown
     */
    public static function set($name, $obj = null)
    {
        if (is_object($name)) {
            return self::$store[$name->name] = $name;
        } else {
            return self::$store[$name] = $obj;
        }
    }

    protected function __clone()
    {
    }
}