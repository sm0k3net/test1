<?php

use X4\Classes\XNameSpaceHolder;
use X4\Classes\XRegistry;


interface xCommonInterface
{
    public function defineFrontActions();
}

interface repoProvider
{
    public function repoExport($objId, $params = array());

    public function repoImport();
}

interface xPluginListener
{
}

interface xModuleListener
{
}

interface xPluginTpl
{
}

interface xModuleTpl
{
}

class xCore
{
    private static $plugins;
    private static $pluginsListeners;
    private static $moduleListeners;
    private static $jsList;
    private static $moduleFrontAction;

    public static function getLicense()
    {
        return HTTP_HOST . '|' . self::getCurrentLicense();
    }

    public static function getCurrentLicense()
    {
        return file_get_contents(PATH_ . 'license');
    }

    public static function checkHostDomain($host)
    {
        if (self::getCurrentLicense() == self::getLicenseFromHost($host)) {
            return true;
        } else {
            return false;
        }
    }

    public static function memoryUsage()
    {
        $mem_usage = memory_get_usage(true);
        if ($mem_usage < 1024) {
            return $mem_usage . ' bytes';
        } elseif ($mem_usage < 1048576) {
            return round($mem_usage / 1024, 2) . ' kilobytes';
        } else {
            return round($mem_usage / 1048576, 2) . ' megabytes';
        }
    }

    public static function getLicenseFromHost($host)
    {
        if (($handle = fopen("http://$host/license", 'r')) or ($handle = fopen("http://www.$host/license", 'r'))) {
            $license = fread($handle, 1024);
            fclose($handle);

            return trim($license);
        }
    }

    public static function getVersion()
    {
        return X4_VERSION;
    }

    public static function incModuleFactory($classname, $call = true)
    {

        $classname[0] = strtoupper($classname[0]);
        $filepath = xConfig::get('PATH', 'CLASSES') . $classname . '.php';
        {
            require_once $filepath;

            if ($call) {

                $classname = implode("\\", array('X4', 'Classes', $classname));
                return new $classname();
            }
        }
    }

    /**
     * Существует ли модуль.
     *
     * @param mixed $name
     */
    public static function isModuleExists($name)
    {
        static $moduleList;

        if (isset($moduleList[$name])) return $moduleList[$name];

        if ((file_exists(xConfig::get('PATH', 'MODULES') . $name . '/js/' . $name . 'Back.js'))) {

            return $moduleList[$name] = true;

        } else {

            return $moduleList[$name] = false;
        }
    }


    public static function getModuleListener($module)
    {
        return self::$moduleListeners[$module];
    }


    /**
     * Метод для wake'upа всех листенеров всех плагинов.
     */
    public static function moduleEventDetector()
    {

        $modules = self::discoverModules();

        if (!empty($modules)) {
            foreach ($modules as $module) {
                if (file_exists(
                    $listnerClass = xConfig::get('PATH', 'MODULES') . $module['name'] . '/' . $module['name'] . '.listener.class.php')) {

                    include_once $listnerClass;
                    $classname = $module['name'] . 'Listener';
                    self::$moduleListeners[$module['name']] = new $classname();
                }
            }
        }
    }

    public static function pluginEventDetector()
    {
        if ($plugs = self::pluginsList()) {
            foreach ($plugs as $pModuleName => $pModule) {
                foreach ($pModule as $plugName => $plugFullName) {
                    if (file_exists(
                        $pluginListener = xConfig::get('PATH',
                                'PLUGINS') . $plugFullName . '/' . $plugName . '.listener.class.php')) {
                        include_once $pluginListener;
                        $classname = $plugName . 'Listener';
                        $listener = self::$pluginsListeners[$pModuleName][$plugName] = new $classname();

                        $config = self::loadPluginConfig($plugFullName);

                        $listener->setConfig($config);
                    }
                }
            }
        }
    }

    public static function getModulePluginsListeners($module, $plugin = null)
    {
        if ($module && $plugin) {
            return self::$pluginsListeners[$module][$plugin];
        } else {
            return self::$pluginsListeners[$module];
        }
    }

    public static function getModulePlugins($module, $plugin = null)
    {
        $plugins = self::pluginsList();

        if ($module && $plugin) {
            return $plugins[$module][$plugin];
        } else {
            return $plugins[$module];
        }
    }

    /**
     * получить все плагины из папки /x4/modules/
     * в виде ассоциативного массива array[moduleName][pluginName].
     */
    public static function moduleFrontActionsList($module)
    {
        if (!empty(self::$moduleFrontAction[$module])) {
            return self::$moduleFrontAction[$module];
        }

        $actionList = XFILES::filesList(xConfig::get('PATH', 'MODULES') . $module . '/actions', 'files', null, 0, true);

        if (!empty($actionList)) {
            foreach ($actionList as $action) {
                $actionPart = explode('.', $action);
                self::$moduleFrontAction[$actionPart[0]] = $action;
            }

            return self::$moduleFrontAction;
        }
    }

    public static function pluginsList()
    {
        if (!empty(self::$plugins)) {
            return self::$plugins;
        }

        if ($plugs = XFILES::directoryList(xConfig::get('PATH', 'PLUGINS'))) {
            foreach ($plugs as $plug) {
                $plugParts = explode('.', $plug);
                self::$plugins[$plugParts[0]][$plugParts[1]] = $plug;
            }

            return self::$plugins;
        }
    }

    /**
     * Factory для вызова плагинов x4
     * возможен вызов модуля(его составных частей) в следующих кобинациях
     * xCore::moduleFactory('pluginName.front');
     * xCore::moduleFactory('pluginName.back');
     * xCore::moduleFactory('pluginName.cron');
     * xCore::moduleFactory('pluginName.xfront');
     * при вызове плагина генерируюся события  moduleName.pluginName:beforeInit
     * при вызове плагина генерируюся события  moduleName.pluginName:afterInit.
     *
     * @param string $modulename - имя плагина
     */
    public static function pluginFactory($plugin)
    {
        $plugin = explode('.', $plugin);
        $loadPrefix = '';
        if (count($plugin) == 3 or count($plugin) == 4) {
            $loadPrefix = array_shift($plugin);
            $loadPrefix .= '.';
        }

        if (XRegistry::exists($plugin[0] . '.' . $plugin[1])) {
            return XRegistry::get($plugin[0] . '.' . $plugin[1]);
        }

        $name = $plugin[1];
        $name[0] = strtoupper($name[0]);
        $classname = $plugin[0] . $name;


        $config = self::loadPluginConfig($loadPrefix . $plugin[0]);

        if ($plugin[1] == 'xfront' or $plugin[1] == 'api') {
            require_once xConfig::get('PATH', 'PLUGINS') . $loadPrefix . $plugin[0] . '/' . $plugin[0] . '.front.class.php';
        }

        if ($plugin[1] == 'api') {
            $plugin[1] = $plugin[1] . '.' . $plugin[2];

            $apiType = $plugin[2];
            $apiType[0] = strtoupper($apiType[0]);
            $classname .= $apiType;

        }


        $moduleInstancePath = xConfig::get('PATH', 'PLUGINS') . $loadPrefix . $plugin[0] . '/' . $plugin[0] . '.' . $plugin[1] . '.class.php';

        if (!file_exists($moduleInstancePath)) {
            return false;
        }

        require_once $moduleInstancePath;

        if ($plugin[1] == 'command') return;

        if (class_exists($classname)) {

            //все конструкторы класса без параметров

            XRegistry::get('EVM')->fire($plugin[0] . '.' . $plugin[1] . ':beforeInit');

            if (strstr($plugin[1], 'api.')) {
                $plugged = 'front';
            } else {
                $plugged = $plugin[1];
            }

            XRegistry::set($plugin[0] . '.' . $plugin[1], $instance = new $classname($plugin[0], self::moduleFactory($loadPrefix . $plugged)));

                          if (method_exists($instance, 'setConfig')) {
                $instance->setConfig($config);
            }

            if ($plugin[1] == 'xfront') {
                XNameSpaceHolder::addObjectToNS('plugin.' . $loadPrefix . $plugin[0] . '.xfront', $instance);
            }

            if ($plugin[1] == 'back') {
                XNameSpaceHolder::addObjectToNS('plugin.' . $loadPrefix . $plugin[0] . '.back', $instance);
            }

            XRegistry::get('EVM')->fire($plugin[0] . '.' . $plugin[1] . ':afterInit', array('instance' => $instance));


            return XRegistry::get($plugin[0] . '.' . $plugin[1]);
        }
    }



    public static function listenToXoad()
    {
        if (isset($_REQUEST['xoadCall'])) {

            $connector = new connector();

            @ob_end_clean();
            ob_start();

            if (XOAD_Server::runServer()) {
                if ($all = ob_get_contents()) {
                    @ob_end_clean();

                    if (xConfig::get('GLOBAL', 'outputHtmlCompress')) {
                        echo Common::compressOutput($all);
                    } else {
                        echo $all;
                    }
                }
            }

            exit();
        }
    }

    /**
     * Factory для вызова модулей x4
     * возможен вызов модуля(его составных частей) в следующих кобинациях
     * xCore::moduleFactory('moduleName.front');
     * xCore::moduleFactory('moduleName.back');
     * xCore::moduleFactory('moduleName.cron');
     * xCore::moduleFactory('moduleName.xfront');
     *  при вызове плагина генерируюся события  moduleName:beforeInit
     * при вызове плагина генерируюся события  moduleName:afterInit.
     *
     * @param string $modulename - имя модуля
     */


    public static function moduleFactory($modulename)
    {
        $xRegCheck = explode('.', $modulename);

        $xRegName = $xRegCheck[0] . strtoupper($xRegCheck[1][0]) . substr($xRegCheck[1], 1);

        if (XRegistry::exists($xRegName)) {
            return XRegistry::get($xRegName);
        }

        $module = explode('.', $modulename);

        self::loadModuleConfig($module[0]);

        $config = xConfig::get('MODULES', $module[0]);

        if ($config['disable']) return;


        self::callCommonInstance($module[0]);

        $branch = $module[1];


        //в случае xfront должен быть подключен модуль front
        if (($module[1] == 'xfront') && (file_exists($inst = xConfig::get('PATH', 'MODULES') . $module[0] . '/' . $module[0] . '.front.class.php'))) {
            require_once xConfig::get('PATH', 'MODULES') . $module[0] . '/' . $module[0] . '.front.class.php';
        }

        if ($module[1] == 'cron') {
            self::moduleFactory($module[0] . '.back');
        }

        if ($module[1] == 'api') {
            $modType = $module[2];
            $modType[0] = strtoupper($modType[0]);
            $module[1] = 'api.' . $module[2];
            $branch = 'api' . $modType;
        }

        $moduleInstancePath = xConfig::get('PATH', 'MODULES') . $module[0] . '/' . $module[0] . '.' . $module[1] . '.class.php';


        if (!file_exists($moduleInstancePath)) {
            throw new Exception('module-class-is-not-exists');
        }

        require_once $moduleInstancePath;

        if ($module[1] == 'command') return;

        $branch[0] = strtoupper($branch[0]);

        //calling class
        if (class_exists($classname = $module[0] . $branch)) {
            //все конструкторы класса без параметров
            XRegistry::get('EVM')->fire($modulename . ':beforeInit');
            XRegistry::set($classname, $m = new $classname());
            XRegistry::get('EVM')->fire($modulename . ':afterInit', array('instance' => $m));
            xConfig::set('calledModules', $module[0], $m);
            //готовый класс
            return $m;
        }
    }

    //to do
    public static function dieWithCode($code)
    {
        require_once xConfig::get('PATH', 'INC') . 'statusCodes.php';

        if ($statusCodes[$code]) {
            echo $statusCodes[$code];
            die();
        } else {
            echo 'Unknown error code-' . $code;
            die();
        }
    }

    public static function show404Page()
    {

        $pages = xCore::moduleFactory('pages.front');
        $langVersion = $pages->langVersion;

     //   header('HTTP/1.0 404 Not Found');

        if (empty($langVersion['params']['default404Page'])) {

            XRegistry::get('TMS')->addFileSection(xConfig::get('PATH', 'TEMPLATES') . '404.htm');
            XRegistry::get('TMS')->addMassReplace('error404', array(
                'link_main_page' => HOST,
                'admin_email' => 'mailto:' . xConfig::get('GLOBAL', 'admin_email')
            ));

            echo XRegistry::get('TMS')->parseSection('error404');
            

        } else {


            $langVersionUrl = '';

            if ($pages->domain['params']['StartPage'] != $pages->langVersion['id']) {
                $langVersionUrl = $pages->langVersion['basic'];
            }

             $url404 = $langVersionUrl . $langVersion['params']['default404Page'];
			
            xConfig::set('PATH', 'baseUrl', $url404);


            $pages->modulesOrder = null;
            $pages->execModules = null;
            $TPA = new pageAgregator(0);
			$TPA->renderMode='NORMAL';
            echo $TPA->executePage($url404);
            die();

        }

    }

    private static function licenseNotify()
    {
        @mail('info@x4.by', 'X4', REAL_HTTP_HOST);
        self::dieWithCode('NO_LICENSE_FILE_DETECTED');
    }

    public static function checkLicense()
    {
        $key = '{%F:key%}';
        $randonSeed = '{%F:r_seed%}';
        $version = '{%F:version%}';

        if ($fp = @fopen('license', 'r')) {
            $string = base64_decode(trim(fread($fp, 256)));
        } else {
            self::licenseNotify();
            die('Ошибка считывания лицензии');
        }

        $key = strrev($key);
        $result = '';

        for ($i = 0; $i < strlen($string); ++$i) {
            $char = substr($string, $i, 1);
            $keychar = substr($key, ($i % strlen($key)) - 1, 1);
            $char = chr(ord($char) - ord($keychar + strlen($string)));
            $result .= $char;
        }

        $version = substr($result, 0, 5);

        if ($version != $_version) {
            self::licenseNotify();
            die('Версия не совпадает');
        }

        $year = substr($result, 5, 10);

        if ($year < mktime(0, 0, 0, date('m'), date('d'), date('Y'))) {
            self::licenseNotify();
            die('Срок лицензии истек');
        }

        if (md5(base64_encode(strrev(REAL_HTTP_HOST . $r_seed))) != substr($result, 15, 32)) {
            self::licenseNotify();
            die('Ошибка лицензии');
        }
    }


    public static function loadPluginConfig($plugin)
    {
        $exp = explode('.', $plugin);
        $configFile = xConfig::get('PATH', 'PLUGINS') . $plugin . '/' . $exp[1] . '.config.php';

        if (file_exists($configFile)) {
            include($configFile);
            $config = xConfig::saveLastSetted($plugin);
            xConfig::set('PLUGINS', $plugin, $config);
            return $config;
        }

        return array();
    }


    public static function discoverPlugins($module)
    {
        $plugins = self::pluginsList();

        if (isset($plugins[$module])) {
            foreach ($plugins[$module] as $kPlug => $plugin) {
                $config = self::loadPluginConfig($plugin);
                if (!empty($config)) {

                    if (!empty($config['disable']) && $config['disable'] === true) continue;

                    if (!empty($_SESSION['lang'])) {
                        $l = Common::getModuleLang($plugin, $_SESSION['lang']);
                        $data[$kPlug]['pluginName'] = $l[$kPlug] ? $l[$kPlug] : $kPlug;
                    }else{
                        $data[$kPlug]['pluginName'] = $kPlug;
                    }

                    $data[$kPlug]['config'] = $config;
                    $data[$kPlug]['name'] = $plugin;


                    $exp = explode('.', $plugin);

                    if (file_exists(xConfig::get('PATH', 'PLUGINS') . $plugin . '/js/' . $exp[1] . 'Back.js')) {
                        $data[$kPlug]['jsFile'] = $plugin;
                    } else {
                        $data[$kPlug]['jsFile'] = false;
                    }

                }
            }

            return $data;
        }
    }

    public static function discoverModules()
    {
        if ($modulesListDirs = XFILES::filesList(xConfig::get('PATH', 'MODULES'), 'directories', null, 0, true)) {
            $modules = array();

            foreach ($modulesListDirs as $moduleDir) {

                $configFile = xConfig::get('PATH', 'MODULES') . $moduleDir . '/' . $moduleDir . '.config.php';

                if (file_exists($configFile)) {
                    include $configFile;
                    $modules[$moduleDir] = xConfig::saveLastSetted($moduleDir);

                    if (!empty($_SESSION['lang'])) {
                        $l = Common::getModuleLang($moduleDir, $_SESSION['lang']);
                        $modules[$moduleDir]['moduleName'] = $l[$moduleDir] ? $l[$moduleDir] : $moduleDir;
                    } else {
                        $modules[$moduleDir]['moduleName'] = $moduleDir;
                    }

                    $modules[$moduleDir]['name'] = $moduleDir;

                    if (empty($modules[$moduleDir]['admSortIndex'])) {
                        $modules[$moduleDir]['admSortIndex'] = 0;
                    }

                    $modules[$moduleDir]['plugins'] = self::discoverPlugins($moduleDir);
                }
            }

            $modulesSort = XARRAY::sortByField($modules, 'admSortIndex', 'dsc');

            return $modulesSort;
        }
    }


    public static function loadModuleConfig($module)
    {
        $configFile = xConfig::get('PATH', 'MODULES') . $module . '/' . $module . '.config.php';

        if (file_exists($configFile)) {
            include($configFile);
            $config = xConfig::saveLastSetted($module);
            xConfig::set('MODULES', $module, $config);
        }

    }


    public static function loadCommonClass($module)
    {
        self::callCommonInstance($module);
        self::loadModuleConfig($module);
        return call_user_func($module . 'Common::getInstance', $module . 'Common');
    }


    public static function callCommonInstance($module)
    {
        $path = xConfig::get('PATH', 'MODULES') . $module . '/' . $module . '.common.class.php';
        if (file_exists($path)) {
            require_once xConfig::get('PATH', 'MODULES') . $module . '/' . $module . '.common.class.php';
        }
    }

    public static function websiteLockerListener()
    {
        if (self::checkLock() == 503) {
            header('HTTP/1.1 503 Service Temporarily Unavailable');
            header('Status: 503 Service Temporarily Unavailable');
            header('Retry-After: 300');
			echo 'Maintaining in progress';
			die();
        }

    }

    public static function checkLock()
    {
        $file = xConfig::get('PATH', 'MEDIA') . 'lock';

        if (file_exists($file)) {
            $data = file_get_contents($file);
            $data = unserialize($data);
	
            if (time() < ($data['timeout'])) {
			
                return $data['code'];

            } else {
                unlink($file);
                return false;
            }
        }
        return false;

    }

    public static function unlockSite()
    {
        $file = xConfig::get('PATH', 'MEDIA') . 'lock';
        if (file_exists($file)) {
    
			unlink($file);
        }

    }


    public static function lockSite($code, $timeout = 50)
    {
        if (XFILES::isWritable(xConfig::get('PATH', 'MEDIA'))) {
            XFILES::fileWrite(xConfig::get('PATH', 'MEDIA') . 'lock', serialize(array('code' => $code, 'timeout' => (time() + $timeout))));
        }

    }
}
