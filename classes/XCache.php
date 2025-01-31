<?php

namespace X4\Classes;

interface XCacheDriver
{
    public static function initDriver();

    public static function serializedRead($module, $id, $timeout = null);

    public static function serializedWrite($data, $module, $id);

    public static function clear($module, $id);

    public static function read($module, $id, $timeout = null);

    public static function write($data, $module, $id, $timeout = null);

    public static function clearBranch($modules);
}

class XCache
{
    static $driver;
    static $readSize = 0;
    static $writeSize = 0;
    static $itemsRead = 0;
    static $itemsWrite = 0;
    static $cacheFileLog = array();

    public static function cacheReadSize($size = 0)
    {
        self::$readSize += $size;
        self::$itemsRead++;
    }

    public static function getCacheLog()
    {
        return self::$cacheFileLog;
    }

    public static function cacheLog($file)
    {
        if (!$_SESSION['fileCacheLog']) $_SESSION['fileCacheLog'] = array();
        if (!isset(self::$cacheFileLog[$file])) self::$cacheFileLog[$file] = 0;
        self::$cacheFileLog[$file]++;
        if (!isset($_SESSION['fileCacheLog'][$file])) $_SESSION['fileCacheLog'][$file] = 0;
        $_SESSION['fileCacheLog'][$file]++;

    }


    public static function cacheWriteSize($size = 0)
    {
        self::$writeSize += $size;
        self::$itemsWrite++;
    }


    public static function getCacheReadSize()
    {
        return array('readSize' => self::$readSize, 'itemsRead' => self::$itemsRead);
    }

    public static function getCacheWriteSize()
    {
        return array('writeSize' => self::$writeSize, 'itemsWrite' => self::$itemsWrite);
    }


    public static function getCurrentDriver()
    {
        return self::$driver;
    }

    /**
     * инициализация механизма кеширования..
     *
     * @param mixed $driver - File,MemCache,Auto
     */
    public static function initialize($driver = 'File')
    {
        if ($driver == 'Auto') {

            if (class_exists('Memcache')) {
                $driver = 'MemCache';
            } else {
                $driver = 'File';
            }
        }

        $driverName = 'XCache' . $driver . 'Driver';
        \Common::loadDriver('XCache', $driverName);
        self::$driver = $driverName;
        self::initDriver();
    }

    final public static function __callStatic($chrMethod, $arrArguments = array())
    {
        if (isset(self::$driver)) {
            return call_user_func_array(self::$driver . '::' . $chrMethod, $arrArguments);
        } else {
            throw new \Exception('cache-driver-did-not-initialized');
        }
    }
}
