<?php

use X4\Classes\MultiSection;
use X4\Classes\PerfomanceMonitor;
use X4\Classes\XCache;
use X4\Classes\XNameSpaceHolder;
use X4\Classes\XRegistry;

class pageAgregator
{
    public $pageNode;
    public $calledModules = array();
    public $modulesOut;
    public $globalFields = array();
    public $modulesList;
    public $disablePageCaching = false;
    public $externalMeta;
    public $mainTemplate;
    public $frontEditMode = false;
    public $TMS = null;
    public $requestAction;
    public $requestActionSub;
    public $requestActionQuery;
    public $requestActionPath;
    public $pageGenerationStart;
    public $enablePerfomanceMonitor = false;
    public $renderMode;
    public $preventMainTemplateProcessing = false;
    public $headlessSlotz = [];

    /*NORMAL; HEADLESS*/

    public function setRenderMode($mode)
    {
        $this->renderMode = $mode;
    }

    public function getRenderMode()
    {
        return $this->renderMode;
    }

    public function executePage($webPagePath)
    {
        try {


            if (!$this->buildPage($webPagePath)) {

                $this->showError404Page();

            } else {
                if (!$this->preventMainTemplateProcessing) {
                    return $this->processMainTemplate();
                }
            }
        } catch (Exception $e) {


            echo $e->getMessage();

            $this->showError404Page();

        }

    }

    public function __construct($generationStarted)
    {
        $this->_TMS = new MultiSection();
        $this->_EVM = XRegistry::get('EVM');
        $this->pageGenerationStart = $generationStarted;

        $this->enablePerfomanceMonitor = xConfig::get('GLOBAL', 'enablePerfomanceMonitor');
        XNameSpaceHolder::addMethodsToNS('TPA', array('setGlobalField', 'getGlobalField'), $this);
    }

    public function setGlobalField($params)
    {
        if (!empty($params)) {
            $k = key($params);
            $this->globalFields[$k] = $params[$k];
        }
    }

    public function getGlobalField($params)
    {
        if (is_array($params['key'])) {
            foreach ($params['key'] as $key) {
                $out[$key] = $this->globalFields[$key];
            }

            return $out;

        } else {

            if (!empty($this->globalFields[$params['key']])) {
                return $this->globalFields[$params['key']];

            } else {

                return false;
            }

        }


    }

    /**
     * //this function in unusable
     * @param $action
     */

    public function dispatchAction($action)
    {
        $TMS = XRegistry::get('TMS');

        switch ($action) {
            case 'robots':
                $TMS->addFileSection(xConfig::get('PATH', 'TEMPLATES') . '/robots.txt');
                $TMS->addReplace('robots', 'host', HTTP_HOST);
                header('Content-Type: text/plain');
                echo $TMS->parseSection('robots');
                break;

            case 'sitemap':
                header('Content-type: application/xml; charset="utf-8"', true);
                echo file_get_contents(xConfig::get('PATH', 'SITEMAP'));
                break;
                die();
        }
    }

    public function pageAccessDenied($reason, $usercp = null)
    {
        /*$pages = xCore::moduleFactory('pages.front');

        if ($usercp) {
            $this->move301Permanent(CHOST.'/'.$pages->_commonObj->createPagePath($usercp, true));
        } else {
            if ($server = $pages->_commonObj->get_page_module_servers('user_panel')) {
                $s = current($server);
                $this->page_redirect_params['fusers']['reason'] = $reason;
                $this->buildPage($pages->_commonObj->createPagePath($s['id'], true).'/~needauth/', true);
            }
        }      */
    }

    /***
     * Передача внешних данных для мета-тегов
     *
     */

    private function setExternalMeta()
    {
        if (isset($this->externalMeta)) {
            foreach ($this->externalMeta as $key => $meta) {
                $this->globalFields[$key] = $meta;
            }
        }
    }

    /***
     * Подключение ajax коннектора xoad
     *
     */
    private function loadAjaxConnector()
    {
        $this->_TMS->addFileSection(xConfig::get('PATH', 'BASE') . '/tpl/connector.html');

        $this->_TMS->addMassReplace('connector', array(
            'enableJsonDebugging' => xConfig::get('GLOBAL', 'enableJsonDebugging'),
            'xConnector' => XOAD_Client::register(new connector()),
            'xoadHeader' => XOAD_Utilities::header(xConfig::get('WEBPATH', 'XOAD')),
            'frontEditor' => $_SESSION['fronted']['enabled'],
            '__uid' => $GLOBALS['__uid'],
        ));

        $this->globalFields['XFRONT_API'] = $this->_TMS->parseSection('connector');
    }

    /*
    *  array('Title'=>'','Description'=>'','Keywords'=>'','Meta'=>'any meta tag')
    */

    public function setSeoData($seoData)
    {
        if ($sData = XRegistry::get('EVM')->fire('agregator:onSetSeoData', $seoData)) {
            if ($sData['Title'] or $sData['Canonical'] or $sData['Description']) {
                $seoData = $sData;
            }
        }

        if (isset($seoData['seo'])) {
            $seoData = $seoData['seo'];
        }

        foreach (array(
                     'Title',
                     'Description',
                     'Keywords',
                     'Meta',
                     'Canonical',
                 ) as $seo) {
            if (!empty($seoData[$seo])) {
                $this->globalFields[$seo] = $seoData[$seo];
            }
        }
    }

    private function processMainTemplate()
    {
        $pages = xCore::moduleFactory('pages.front');

        /*
         * Подключение шаблона страницы если она не является страницей на главном шаблоне
         */

        if (!strstr($this->mainTemplate['path'], '_index.html')) {

            $mt = explode('/', $this->mainTemplate['path']);

            $this->_TMS->addFileSection(xConfig::get('PATH', 'COMMON_TEMPLATES') . $mt[1]);
        }

        /*
         * Подключение главного шаблона для домена
         */

        $this->_TMS->addFileSection(
            xConfig::get('PATH', 'COMMON_TEMPLATES') . '/_index.html');


        $this->globalFields['HOST'] = HOST;
        $this->globalFields['PAGE'] = $pages->page;
        $this->globalFields['DOMAIN'] = $pages->domain;
        $this->globalFields['LANGVERSION'] = $pages->langVersion;
        $this->globalFields['ARES'] = xConfig::get('WEBPATH', 'ARES');
        $this->globalFields['JS'] = jsCollector::get('main', $this->globalFields['compressMainJs']);
        $this->loadAjaxConnector();
        $this->setExternalMeta();


        if (!$this->globalFields['Title'] or ($pages->page['params']['DoNotSuppressTitle'])) {
            $pages = XRegistry::get('pages');
            $seoFields = $this->globalFields['PAGE'];

            $this->setSeoData(array(
                'Title' => $seoFields['params']['Title'],
                'Description' => $seoFields['params']['Description'],
                'Keywords' => $seoFields['params']['Keywords'],
                'Canonical' => $seoFields['params']['Canonical'],
                'Meta' => $seoFields['params']['Meta'],
            ));
        }

        //компонуем модули
        $this->_TMS->addMFMassReplace($this->slotzOut);

        if (isset($this->_TMS->sectionNests['MAIN'])) {
            foreach ($this->_TMS->sectionNests['MAIN'] as $section) {
                $this->_TMS->addMassReplace($section, $this->globalFields);
            }

            return $this->_TMS->parseRecursive('MAIN');
        }
    }

    public static function reverseRewrite($url)
    {
        $pages = xCore::moduleFactory('pages.front');

        if ($rewrites = $pages->getRewrites()) {
            foreach ($rewrites as $rewrite) {

                $rewrite['from'] = str_replace(array('/'), array('\/'), $rewrite['from']);

                if ($rewrite['full']) {

                    $url = str_replace(array('\\', $rewrite['to']), array('', $rewrite['from']), $url);

                }
            }
            return $url;

        }
    }

    public function rewrite($pagePath)
    {
        $pages = xCore::moduleFactory('pages.front');
        $z = false;
        $rewrites = $pages->getRewrites();
        if (!empty($rewrites)) {

            foreach ($rewrites as $rewrite) {
                $rewrite['from'] = str_replace(array('/'), array('\/'), $rewrite['from']);

                if ($rewrite['full']) {
                    $rewrite['from'] = str_replace('\\', '', $rewrite['from']);

                    $pagePathTemp = $pagePath;
                    $pagePathArr = explode('?', $pagePath);
                    $pagePath = $pagePathArr[0];

                    if ($rewrite['from'] == $pagePath or $rewrite['from'] . '/' == $pagePath) {
                        $w = $rewrite['to'];
                        if (!empty($pagePathArr[1])) {

                            if (strstr($w, '?')) {
                                $symbol = '&';
                            } else {
                                $symbol = '?';
                            }
                            $w .= $symbol . $pagePathArr[1];
                        }
                    } else {

                        $pagePath = $pagePathTemp;
                    }


                } else {

                    $w = preg_replace('/' . $rewrite['from'] . '/', $rewrite['to'], $pagePath);
                }

                if ($w) {
                    if ($w !== $pagePath) {
                        if ((int)$rewrite['is301']) {
                            $this->move301Permanent($w);

                        }

                        return $w;
                    }
                }
            }
        }
    }

    public function getCurrentPage()
    {
        return $this->currentPageNode;
    }

    public function getRequestActionInfo()
    {
        return array(
            'requestAction' => $this->requestAction,
            'requestActionSub' => $this->requestActionSub,
            'requestActionQuery' => $this->requestActionQuery,
            'requestActionPath' => $this->requestActionPath,
            'pageLink' => $this->pageLink,
            'pageLinkHost' => $this->pageLinkHost,
            'requestData' => $_REQUEST,
            'getData' => $_GET,
        );
    }

    public function executeModuleAction($moduleType, $moduleParams, $moduleId = null)
    {
        if (is_object($moduleObject = xCore::moduleFactory($moduleType . '.front'))) {
            $moduleObject->requestActionSet($this->requestAction);
            return $moduleResult = $moduleObject->execute($moduleParams, $moduleId);
        }
    }

    public function parsePagePath($pagePath)
    {
        $pagePath = urldecode($pagePath);
        /*$filter='/[^\w\s\/@!?\.\?\]\[\=\~\-]+|(?:\.\/)|(?:@@\w+)|(?:\+ADw)|(?:union\s+select)/i';
                
        if (preg_match($filter, $pagePath, $matches)) {
            return false;
        }
        */


        $parsedPagePath = parse_url($pagePath);

        $pathExploded = explode('/~', $parsedPagePath['path']);

        $this->pageLink = $_SESSION['pages']['currentPagePath'] = $pathExploded[0];

        $this->pageLinkHost = CHOST . $this->pageLink;

        if (isset($pathExploded[1])) {
            $this->requestAction = strtok($pathExploded[1], '/');

            $this->requestActionPath = substr($pathExploded[1], strlen($this->requestAction));

            //last trailing slash
            if (substr($this->requestActionPath, -1) == '/') {
                $this->requestActionPath = substr($this->requestActionPath, 0, -1);
            }


        }

        if (isset($parsedPagePath['query'])) {
            $this->requestActionQuery = $parsedPagePath['query'];
        }

        /*if (isset($pathExploded[0])) {
            if (preg_match('/[0-9a-z_\-\/]/', $pathExploded[0])) {
                $treePath = XARRAY::clearEmptyItems(explode('/', $pathExploded[0]), true);
            } else {
                return false;
            }
        }*/

        return $pathExploded;
    }

    public function getInlineDebugInfo()
    {

        $generationTime = Common::getmicrotime() - $this->pageGenerationStart;
        $memoryConsumed = XFILES::formatSize(memory_get_usage(true));

        $debugArray = [];
        $debugArray[] = $_SERVER['REQUEST_URI'];
        $debugArray[] = 'Generation time:' . $generationTime;

        XRegistry::get('logger')->info(implode("\r\n", $debugArray));

        $cacheDataRead = XCache::getCacheReadSize();
        $cacheDataWrite = XCache::getCacheWriteSize();

        $debugArray = [];
        $debugArray[] = 'Cache size:' . XFILES::formatSize($cacheDataRead['readSize']);
        $debugArray[] = 'Cache items read size:' . $cacheDataRead['itemsRead'];
        $debugArray[] = 'Cache write size:' . XFILES::formatSize($cacheDataWrite['writeSize']);
        $debugArray[] = 'Memory consumed:' . $memoryConsumed;

        if ($this->enablePerfomanceMonitor) {
            $perfomanceMonitor = new PerfomanceMonitor();
            $perfomanceMonitor->logPageTime($_SERVER['REQUEST_URI'], $generationTime, null);
            $avg = $perfomanceMonitor->getAvgTime($_SERVER['REQUEST_URI']);
            $debugArray[] = 'AVG:' . print_r($avg, true);
        }

        XRegistry::get('logger')->info(implode("\r\n", $debugArray));


    }

    public function dispatcher()
    {

        if (xConfig::get('GLOBAL', 'enableLogs') && xConfig::get('GLOBAL', 'enableBrowserConsoleLogs')) {

            XRegistry::get('logger')->pushHandler(new \Monolog\Handler\BrowserConsoleHandler(\Monolog\Logger::INFO));
        }


        if (isset($_GET['x4action'])) {
            $this->dispatchAction($_GET['x4action']);
        } elseif ($page = $this->executePage(xConfig::get('PATH', 'baseUrl'))) {
            //remove  double lines
            $all = preg_replace('/\n(\s*\n)+/', "\n\n", $page);

            $inlineDebug = '';

            if (xConfig::get('GLOBAL', 'showDebugInfo')) {

                $inlineDebug = $this->getInlineDebugInfo();
            }

            if (xConfig::get('GLOBAL', 'outputHtmlCompress')) {

                $all = Common::compressOutput($all);
            }

            return $all . $inlineDebug;

        }

    }


    public function buildPage($pagePath, $innerAccess = false, $renderSelectedSlotz = null)
    {


        $this->_EVM->fire('agregator:start', $pagePath);

        if ((!$innerAccess) && $url = $this->rewrite($pagePath)) {

            $w = parse_url($url);

            $innerBack = true;
            if ($w['query']) {
                parse_str($w['query'], $GET);
                $_GET = array_replace_recursive($GET, $_GET);
                $_REQUEST = $_GET;
            }

            if (strstr($url, '/catalog/')) {
                $innerBack = false;
            }

            $eventResult = XRegistry::get('EVM')->fire('agregator:onUrlRewrited', array('url' => $url));

            if (!empty($eventResult)) {
                $url = $eventResult['url'];

                if ($eventResult['rewritten']) {
                    $innerBack = false;
                }

            }


            return $this->buildPage($url, $innerBack, $renderSelectedSlotz);
        }


        $this->modulesOut = null;
        $this->globalFields = array();

        $_SESSION['pages']['previousPagePath'] = $_SESSION['pages']['currentPageFullPath'];
        $this->pageFullLink = $_SESSION['pages']['currentPageFullPath'] = $pagePath;

        if (isset($_SESSION['fronted']['enabled'])) {
            $this->frontEditMode = true;
        }

        if (!$pathExploded = $this->parsePagePath($pagePath)) {
            return;
        }


        $pages = xCore::moduleFactory('pages.front');

        try {

            if (!$pages->getPageIdByPath($pathExploded[0])) {

                return false;
            }

        } catch (Exception $e) {


            if ($e->getMessage() == 'no-domain-detected') {
                //    $this->gotoInstall();
            }

            if ($e->getMessage() == 'headless-301-rebuild') {
                return $this->buildPage($pages->moveLink, true);

            }

        }


        xConfig::set('PATH', 'COMMON_TEMPLATES', xConfig::get('PATH', 'TEMPLATES') . $pages->domain['params']['TemplateFolder'] . '/_common/');
        xConfig::set('PATH', 'MODULES_TEMPLATES', xConfig::get('PATH', 'TEMPLATES') . $pages->domain['params']['TemplateFolder'] . '/_modules/');
        xConfig::set('WEBPATH', 'ARES', HOST . 'project/templates/' . $pages->domain['params']['TemplateFolder'] . '/_ares/');


        $this->currentPageNode = $pages->page;

        if ($_SESSION['POST']) {
            $_POST = $_SESSION['POST'];
            unset($_SESSION['POST']);
        }

        xCore::callCommonInstance('templates');

        $templates = templatesCommon::getInstance();

        if (!$pages->page['params']['Template'] or $pages->page['params']['DisableGlobalLink']) {
            return;
        }


        $this->mainTemplate = $templates->getTpl($pages->page['params']['Template'], $pages->domain['params']['TemplateFolder']);

        $slotzCrotch = $pages->getSlotzCrotch($this->mainTemplate['slotz']);

        if (!empty($renderSelectedSlotz)) {
            foreach ($slotzCrotch as $slot => &$modules) {
                if (!in_array($slot, $renderSelectedSlotz)) {
                    foreach ($modules as $module) {
                        unset($pages->modulesOrder[$module]);
                    }

                    unset($slotzCrotch[$slot]);
                }
            }
        }

        $this->pageMark = md5($pagePath . xConfig::get('cache', 'globalCacheKey'));

        if ($this->renderMode == 'HEADLESS' && xConfig::get('GLOBAL', 'cacheHeadlessSlots')) {
            if ($ext = XCache::serializedRead('cacheHeadlessSlots', $this->pageMark)) {
                $this->headlessSlotz = $ext;
                $this->headlessCache = true;
                $this->_EVM->fire('agregator:end', $pagePath);
                return true;
            }

        }


        //добываем модули слотов ветвления
        if (!empty($pages->modulesOrder)) {
            foreach ($pages->modulesOrder as $moduleId => $priority) {
                if ($module = $pages->execModules[$moduleId]) {

                    $moduleStartTime = Common::getmicrotime();
                    $modulesOut[$moduleId] = $this->executeModuleAction($module['params']['_Type'], $module['params'], $module['id']);

                    $pages->execModules[$moduleId]['executeTime'] = -$moduleStartTime + Common::getmicrotime();
                }
            }
        }


        if (!empty($slotzCrotch)) {
            foreach ($slotzCrotch as $slot => &$modules) {
                foreach ($modules as $moduleId) {
                    if (!$this->frontEditMode) {
                        if (!isset($this->slotzOut[$slot])) {
                            $this->slotzOut[$slot] = '';
                        }

                        $evmResult = $this->_EVM->fire('agregator:beforeRender', array(
                            'output' => $modulesOut[$moduleId],
                            'module' => $pages->execModules[$moduleId]
                        ));

                        if (!empty($evmResult)) {
                            $modulesOut[$moduleId] = $evmResult['output'];
                        }

                        switch ($this->renderMode) {
                            case 'NORMAL':

                                $this->slotzOut[$slot] .= $modulesOut[$moduleId];
                                break;

                            case 'HEADLESS':

                                $this->headlessSlotz[$slot][$moduleId] = array(
                                    'output' => $modulesOut[$moduleId],
                                    'module' => $pages->execModules[$moduleId]
                                );

                                break;
                        }

                    } else {
                        $data = json_encode(
                            array(
                                'executeTime' => $pages->execModules[$moduleId]['executeTime'],
                                'id' => $moduleId,
                                'type' => $pages->execModules[$moduleId]['params']['_Type'],
                            )
                        );

                        $this->slotzOut[$slot] .= '<map class="__x4moduleMap"  data-info=\'' . $data . '\'>' . $modulesOut[$moduleId] . '</map>';
                    }
                }


                if ($this->frontEditMode) {
                    $data = json_encode(
                        array(
                            'id' => $moduleId,
                            'name' => $slot,
                        )
                    );

                    $this->slotzOut[$slot] = '<output  data-info=\'' . $data . '\' class="__x4SlotMap" >' .
                        $this->slotzOut[$slot] . '</output>';
                }
            }
        }


        if ($this->renderMode == 'HEADLESS' && xConfig::get('GLOBAL', 'cacheHeadlessSlots')) {
            XCache::serializedWrite($this->headlessSlotz, 'cacheHeadlessSlots', $this->pageMark);
        }

        $this->_EVM->fire('agregator:end', $pagePath);

        return true;
    }

    public function move301Permanent($link)
    {
        //preserve POST
        if (is_array($_POST)) {
            $_SESSION['POST'] = $_POST;
        }

        Header('HTTP/1.1 301 Moved Permanently');
        Header('Location:' . $link);
        die();
    }


    public function gotoInstall()
    {
        $this->move301Permanent(HOST . 'install/install.php');
    }

    //если ошибка пути или страница не существует
    public function showError404Page()
    {
        xCore::show404Page();
    }
}
