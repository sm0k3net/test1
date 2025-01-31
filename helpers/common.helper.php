<?php

use X4\Classes\XRegistry;

class Common
{
    public static $pageNavView = 'pages';

    public static $pageMoveChunk = 0;

    public static $parseRadius = 4;

    public static function isFileExists($file)
    {
        if (file_exists(PATH_ . $file) && $file) {
            return true;
        }
    }

    public static function loadDriver($device, $driver)
    {

        require_once(xConfig::get('PATH', 'DRIVERS') . $device . '/' . $driver . '.php');

    }

    public static function  includeAll($folder, array $exclude=array()){


        foreach (glob("{$folder}/*.php") as $filename)
        {
            if(!in_array(basename($filename),$exclude)){
                include $filename;
            }
        }
    }

    public static function compressOutput(&$output, $level = 9)
    {
        if (@$_SERVER["HTTP_ACCEPT_ENCODING"] && FALSE !== strpos($_SERVER["HTTP_ACCEPT_ENCODING"], 'gzip')) {
            $output = gzencode($output, $level);
            header('Content-Encoding: gzip');
            header('Content-Length: ' . strlen($output));
        }

        return $output;
    }

    public static function createMark()
    {
        $markSource = func_get_args();
        return md5(var_export($markSource, true));
    }

    public static function isFolderWriteable($dir)
    {
        if (!is_writeable($dir)) {
            trigger_error('Directory must be writeable : ' . $dir, E_USER_ERROR);
        }
    }


    public static function getTemplateBranches()
    {
        $domains = XFILES::directoryList(xConfig::get('PATH', 'TEMPLATES'));
        return $domains;

    }

    public static function getModuleTemplateListAsSelector($module, $selected, $extensions = array('.html'), $getAliases = true)
    {
        $filesBranched = Common::getModuleTemplateList($module, $extensions);

        if (!empty($filesBranched)) {
            if ($getAliases)
            {
                foreach($filesBranched as $templateBranch=>$files){

                    $aliases = array();
                    foreach ($files as $file) {
                        if ($f = fopen(xConfig::get('PATH', 'TEMPLATES') . $templateBranch . '/_modules/' . $module . '/' . $file, 'r')) {
                            $line = fgets($f);
                            if ($line[0] == '@') {
                                $aliases[] = substr($line, 1) . '(' . $file . ')';
                            } else {
                                $aliases[] = $file;
                            }

                            fclose($f);
                        }
                    }
                    $filesBranched[$templateBranch] = XARRAY::combine($aliases, $files);
                }


            }
        }

        return XHTML::arrayToXoadSelectOptions($filesBranched, $selected, true);

    }

    public static function getModuleTemplateList($module, $extensions = array('.html'))
    {
        $branches=self::getTemplateBranches();        
        $files=array();
        
        foreach($branches as $templateBranch){
            $files[$templateBranch] = XFILES::filesList(xConfig::get('PATH', 'TEMPLATES') . $templateBranch . '/_modules/' . $module, 'files', $extensions, 0, true);
        }
        
        if (!empty($files)) {
            return $files;
        }
    }

    public static function getFrontModuleTplPath($module, $template, $domain = null)
    {
        if (!$domain) {
            return xConfig::get('PATH', 'MODULES_TEMPLATES') . $module . '/' . $template;
        } else {
            return xConfig::get('PATH', 'TEMPLATES') . $domain . '/_modules/' . $module . '/' . $template;
        }
    }

    public static function writeLog($data, $prefix = '', $maxSize = null)
    {
        if (is_array($data)) {
            $data = print_r($data, true);
        }

        if (XFILES::isWritable('log/')) {
            if ($maxSize) {
                $fileSize = filesize('log/log.txt');
                if ($fileSize > $maxSize) {
                    unlink('log/log.txt');
                }

            }

            if ($f = fopen('log/log.txt', 'a+')) {
                fwrite($f, ' [' . date("H:i:s") . '] ' . $prefix . ' ' . $data . "\n\r");
                fclose($f);
            }

        }


    }

    public static function setPageNavRadius($radius)
    {
        xConfig::set('GLOBAL', 'paginatorParseRadius',$radius);

        //depricated
        static::$parseRadius = $radius;
    }


    //depricated

    public static function parseNavPagesHeadless($objCount, $chunkSize, $current, $link, $paginame = 'page', $slashIn = false, $pageLimit = false)
    {

        $dotsPrepend = false;
        $dotsAppend = false;

        if (!$slashIn) {
            $slash = '/?';
        } else {
            $slash = '?';
        }


        if ($chunkSize == 0) return false;

        if ($objCount > $chunkSize) {

            $cpage = 0;

            if (!$chunkSize) {
                $chunkSize = DEFAULT_CHUNK_SIZE;
            }

            $pagesCount = ceil($objCount / $chunkSize);

            if ($moveChunk = Common::$pageMoveChunk) {
                if (($moveChunk * 2 + 1) < $pagesCount) {
                    $moveChunkAll = $moveChunk * 2 + 1;
                } else {
                    $moveChunkAll = $pagesCount;
                }

                $pagesCountCurrent = ceil($current / $chunkSize) + 1;
                if (($moveChunk + $pagesCountCurrent) < $pagesCount) {
                    $movchPagesCount = $moveChunkAll;
                    if ($moveChunk + 1 <= $pagesCountCurrent) {
                        $pagesCountCurrent -= $moveChunk + 1;
                        $movchPagesCount += $pagesCountCurrent;
                    } else {
                        $pagesCountCurrent = 0;
                    }
                } else {
                    $movchPagesCount = $pagesCount;
                    if ($pagesCount > $moveChunkAll) {
                        $pagesCountCurrent = $pagesCount - $moveChunkAll;
                    } else {
                        $pagesCountCurrent = 0;
                    }
                }

                if ($current == 0 && ($moveChunk + $pagesCountCurrent > $pagesCount)) {
                    $movchPagesCount = $pagesCount;
                }

                $pagesRealCount = $movchPagesCount;
            } else {
                $pagesCountCurrent = 0;
                $pagesRealCount = $pagesCount;
            }

            $pageLine = '';
            $i = $pagesCountCurrent;


            $pInfo = XRegistry::get('TPA')->getRequestActionInfo();
            $requestParams = $pInfo['requestActionQuery'];
            $catalog = xCore::moduleFactory('catalog.front');
            $requestParams = preg_replace('/(&?' . $paginame . '=[0-9]+)/i', '', $requestParams);


            $props = [];

            if ($pagesRealCount)
                while ($i < $pagesRealCount) {
                    $i++;

                    $paginameStr = '';

                    if (Common::$pageNavView == 'items') {
                        $pnum = $cpage . '-' . ($cpage + $chunkSize);
                    } elseif (Common::$pageNavView == 'pages') {
                        $pnum = $i;
                    }


                    $cpage = ($i - 1) * $chunkSize;

                    if ($cpage == 0) {

                        if ($requestParams) {
                            $requestParamsStr .= '?' . $requestParams;
                            $flink = $link . '/' . $requestParamsStr;
                        } else {
                            $flink = $link;
                        }

                    } else {

                        if ($pageLimit) {
                            $cpage = $cpage + 1 + $chunkSize;
                        }

                        if ($requestParams) {
                            $paginameStr .= '&' . $paginame;
                        } else {
                            $paginameStr = $paginame;
                        }

                        $flink = $link . $slash . $requestParams . $paginameStr . '=' . $cpage;

                    }
                    $flink = $catalog->_commonObj->buildUrlTransformation($flink);
                    $flink = XRegistry::get('TPA')->reverseRewrite($flink);

                    $flink = str_replace('??', '?', $flink);
                    $flink = str_replace('?&', '?', $flink);

                    $data = array(
                        'link' => $flink,
                        'pnum' => $pnum,
                        'start' => $cpage + 1,
                        'end' => $cpage + 1 + $chunkSize
                    );


                    if ($cpage == $current) {
                        $data['selected'] = true;
                        $props['pages'][] = $data;
                    } else {
                        if (abs($cpage - $current) <= Common::$parseRadius * $chunkSize                    // в радиусе парсинга
                            || $i == 1                                                                // первая
                            || $i == $pagesRealCount                                             // последняя
                            || ($cpage == $chunkSize && -$cpage + $current <= (Common::$parseRadius + 1) * $chunkSize)     // 2я, если активна 5я
                            || ($objCount - $cpage <= 2 * $chunkSize && -$current + $cpage <= (Common::$parseRadius + 1) * $chunkSize)     // то же с другой стороны
                        ) {

                            $props['pages'][] = $data;

                        } else {
                            if ($cpage < $current && !$dotsPrepend) {
                                $props['pages'][] = array('dots' => 'dotsPrepend');
                                $dotsPrepend = true;
                            } else {
                                if ($cpage > $current && !$dotsAppend) {
                                    $props['pages'][] = array('dots' => 'dotsAppend');
                                }
                            }

                        }


                    }

                    $cpage += $chunkSize;
                }


            $linker = $catalog->_commonObj->buildUrlTransformation($link . $slash . $requestParams);


            $linker = XRegistry::get('TPA')->reverseRewrite($linker);

            if (strstr($linker, '?')) {
                $paginameStr = '&' . $paginame;

            } else {

                $paginameStr = '?' . $paginame;
            }

            if (ceil($current / $chunkSize) < $pagesCount - 1) {
                $np = $current + $chunkSize;
                if ($pageLimit) {
                    $np = $np + 1 + $chunkSize;
                }

                $props['next_page'] = array('link' => $linker . $paginameStr . '=' . $np);

            }

            if ($current > $chunkSize) {
                $np = $current - $chunkSize;

                if ($pageLimit) {
                    $np = $np + 1 + $chunkSize;
                }

                $props['previous_page'] = array('link' => $linker . $paginameStr . '=' . $np);
            }

            if (ceil($current / $chunkSize) > 1) {
                $np = 0;
                $props['first_page'] = array('link' => $linker . $paginameStr . '=' . $np);


            }

            if (ceil($current / $chunkSize) < $pagesCount - 2) {
                $np = $pagesCount * $chunkSize - $chunkSize;
                $props['last_page'] = array('link' => $linker . $paginameStr . '=' . $np);

            }

            $props['page_line'] = array('pages_count' => $pagesCount, 'count' => $objCount);

            $props['chunkSize'] = (int)$chunkSize;

            return $props;
        }

    }


    //depricated
    public static function parseNavPages($objCount, $chunkSize, $current, $link, $TMS, $paginame = 'page', $slashIn = false)
    {

        $dotsPrepend = false;
        $dotsAppend = false;

        if (!$slashIn) {
            $slash = '/?';
        } else {
            $slash = '?';
        }

        if ($chunkSize == 0) return false;

        if ($objCount > $chunkSize) {

            $cpage = 0;

            if (!$chunkSize) {
                $chunkSize = DEFAULT_CHUNK_SIZE;
            }

            $pagesCount = ceil($objCount / $chunkSize);

            if ($moveChunk = Common::$pageMoveChunk) {
                if (($moveChunk * 2 + 1) < $pagesCount) {
                    $moveChunkAll = $moveChunk * 2 + 1;
                } else {
                    $moveChunkAll = $pagesCount;
                }

                $pagesCountCurrent = ceil($current / $chunkSize) + 1;
                if (($moveChunk + $pagesCountCurrent) < $pagesCount) {
                    $movchPagesCount = $moveChunkAll;
                    if ($moveChunk + 1 <= $pagesCountCurrent) {
                        $pagesCountCurrent -= $moveChunk + 1;
                        $movchPagesCount += $pagesCountCurrent;
                    } else {
                        $pagesCountCurrent = 0;
                    }
                } else {
                    $movchPagesCount = $pagesCount;
                    if ($pagesCount > $moveChunkAll) {
                        $pagesCountCurrent = $pagesCount - $moveChunkAll;
                    } else {
                        $pagesCountCurrent = 0;
                    }
                }

                if ($current == 0 && ($moveChunk + $pagesCountCurrent > $pagesCount)) {
                    $movchPagesCount = $pagesCount;
                }

                $pagesRealCount = $movchPagesCount;
            } else {
                $pagesCountCurrent = 0;
                $pagesRealCount = $pagesCount;
            }

            $pageLine = '';
            $i = $pagesCountCurrent;


            $pInfo = XRegistry::get('TPA')->getRequestActionInfo();


            $requestParams = $pInfo['requestActionQuery'];
            $catalog = xCore::moduleFactory('catalog.front');
            $requestParams = preg_replace('/(&?' . $paginame . '=[0-9]+)/i', '', $requestParams);


            $sectionPrefix = '';

            if ($pagesRealCount)
                while ($i < $pagesRealCount) {
                    $i++;

                    $paginameStr = '';

                    if (Common::$pageNavView == 'items') {
                        $pnum = $cpage . '-' . ($cpage + $chunkSize);
                    } elseif (Common::$pageNavView == 'pages') {
                        $pnum = $i;
                    }


                    $cpage = ($i - 1) * $chunkSize;

                    if ($cpage == 0) {

                        if ($requestParams) {
                            $requestParamsStr .= '?' . $requestParams;
                            $flink = $link . '/' . $requestParamsStr;
                        } else {
                            $flink = $link;
                        }

                    } else {


                        if ($requestParams) {
                            $paginameStr .= '&' . $paginame;
                        } else {
                            $paginameStr = $paginame;
                        }

                        $flink = $link . $slash . $requestParams . $paginameStr . '=' . $cpage;

                    }


                    $flink = $catalog->_commonObj->buildUrlTransformation($flink);
                    $flink = XRegistry::get('TPA')->reverseRewrite($flink);
                    $flink = stripslashes($flink);
                    $chrCount = count_chars($flink);


                    if ($chrCount[38] > 0 && $chrCount[63] == 0) {


                        $pos = strpos($flink, '&');
                        if ($pos !== false) {
                            $flink = substr_replace($flink, '?', $pos, 1);
                        }

                    }

                    $flink = str_replace('??', '?', $flink);
                    $flink = str_replace('?&', '?', $flink);
                    $flink = str_replace('&?', '?', $flink);

                    $data = array(
                        'link' => $flink,
                        'pnum' => $pnum,
                        'start' => $cpage + 1,
                        'end' => $cpage + 1 + $chunkSize
                    );

                    if ($cpage == $current) {
                        $TMS->addMassReplace('one_page_selected', $data);
                        $pageLine .= $TMS->parseSection('one_page_selected');
                    } else {
                        if (abs($cpage - $current) <= Common::$parseRadius * $chunkSize                    // в радиусе парсинга
                            || $i == 1                                                                // первая
                            || $i == $pagesRealCount                                             // последняя
                            || ($cpage == $chunkSize && -$cpage + $current <= (Common::$parseRadius + 1) * $chunkSize)     // 2я, если активна 5я
                            || ($objCount - $cpage <= 2 * $chunkSize && -$current + $cpage <= (Common::$parseRadius + 1) * $chunkSize)     // то же с другой стороны
                        ) {

                            $TMS->addMassReplace($sectionPrefix . 'one_page', $data);
                            $pageLine .= $TMS->parseSection($sectionPrefix . 'one_page');

                        } else {
                            if ($cpage < $current && !$dotsPrepend) {
                                $pageLine .= $TMS->parseSection($sectionPrefix . 'dots');
                                $dotsPrepend = true;
                            } else {
                                if ($cpage > $current && !$dotsAppend) {
                                    $pageLine .= $TMS->parseSection($sectionPrefix . 'dots');
                                    $dotsAppend = true;
                                }
                            }

                        }
                    }

                    $cpage += $chunkSize;
                }


            $linker = $catalog->_commonObj->buildUrlTransformation($link . $slash . $requestParams);
            $linker = XRegistry::get('TPA')->reverseRewrite($linker);

            if (strstr($linker, '?')) {
                $paginameStr = '&' . $paginame;

            } else {

                $paginameStr = '?' . $paginame;
            }

            if (ceil($current / $chunkSize) < $pagesCount - 1) {
                $np = $current + $chunkSize;
                $TMS->addMassReplace('next_page', array('link' => $linker . $paginameStr . '=' . $np));
                $TMS->parseSection('next_page', true);
            }

            if ($current > $chunkSize) {
                $np = $current - $chunkSize;
                $TMS->addMassReplace('previous_page', array('link' => $linker . $paginameStr . '=' . $np));
                $TMS->parseSection('previous_page', true);
            }

            if (ceil($current / $chunkSize) > 1) {
                $np = 0;
                $TMS->addMassReplace('page_line', array('first_page' => $linker . $paginameStr . '=' . $np));

            }

            if (ceil($current / $chunkSize) < $pagesCount - 2) {
                $np = $pagesCount * $chunkSize - $chunkSize;
                $TMS->addMassReplace('page_line', array('last_page' => $linker . $paginameStr . '=' . $np));

            }

            $TMS->addMassReplace('page_line', array(
                'page_line' => $pageLine,
                'pages_count' => $pagesCount,
                'count' => $objCount
            ));

            return $TMS->parseSection('page_line', true);
        }
    }

    public static function setToUrl($url, $params)
    {

        if (!empty($url)) {
            $parsed = parse_url(urldecode($url));
            parse_str($parsed['query'], $queryArray);
            $query = $parsed['path'];


            if (is_array($queryArray) && $params) {
                $queryArray = array_replace_recursive($queryArray, $params);
            } else {
                $queryArray = $params;
            }

            return $query .= '?' . http_build_query($queryArray);

        }


    }


    public static function &classesFactory($classname, $args, $doNotCall = false)
    {
        if (file_exists($filepath = xConfig::get('PATH', 'CLASSES') . $classname . '.php')) {
            require_once($filepath);
            $classname = 'X4\Classes\\' . $classname;
            if (!$doNotCall) {
                $class = new ReflectionClass($classname);
                $instance = $class->newInstanceArgs($args);
                return $instance;
            }
        }
    }


    public static function getFrontLang($lang)
    {
        $LANG = null;
        $file = xConfig::get('PATH', 'PROJECT') . 'lang/' . $lang . '.php';
        if (file_exists($file)) {
            include($file);
            return $LANG;
        }

    }

    /**
     * Получить языковые данные по модулю
     *
     * @param mixed $module
     * @param mixed $lang
     * @param mixed $tpl
     * @return array
     */
    public static function getModuleLang($module, $lang)
    {
        static $langCache;
        static $commonLang;

        $LANG = null;

        if (!$commonLang) {
            if (file_exists(xConfig::get('PATH', 'ADM') . '/glang/' . $lang . '.lang.php')) {
                require_once(xConfig::get('PATH', 'ADM') . '/glang/' . $lang . '.lang.php');

                $commonLang = $LANG;
                unset($LANG);
            }
        }


        if (!isset($langCache[$module])) {
            if ($module != 'AdminPanel' && file_exists($f = xConfig::get('PATH', 'MODULES') . $module . '/lang/' . $lang . '.lang.php')) {
                require_once($f);

            } elseif ($module == 'AdminPanel' && file_exists($f = xConfig::get('PATH', 'ADM') . 'lang/' . $lang . '.lang.php')) {
                require_once($f);

            } elseif (strstr($module, '.') && file_exists($f = xConfig::get('PATH', 'PLUGINS') . $module . '/lang/' . $lang . '.lang.php')) {
                include($f);

                $modExploded = explode('.', $module);

                $langCache[$modExploded[1]] = array_merge($commonLang, $LANG);

                return $langCache[$modExploded[1]];


            } elseif ($module == 'core' && file_exists($f = xConfig::get('PATH', 'MOD') . 'lang/' . $lang . '.lang.php')) {
                require_once($f);

            }

            // преобразуем переменные для замены

            if (!empty($LANG)) {
                $langCache[$module] = $LANG;
            }
        }

        if (isset($langCache[$module])) {
            return array_merge($commonLang, $langCache[$module]);
        } else {
            return $commonLang;
        }
    }

    public static function getPluginTpl($module, $tpl)
    {
        return xConfig::get('PATH', 'PLUGINS') . $module . '/tpl/' . $tpl;
    }

    public static function getModuleTpl($module, $tpl)
    {
        return xConfig::get('PATH', 'MODULES') . $module . '/tpl/' . $tpl;
    }

    public static function getAdminTpl($tpl)
    {
        return xConfig::get('PATH', 'ADM') . 'tpl/' . $tpl;
    }

    public static function packData($data)
    {
        if ($data) {
            if (is_array($data)) {
                $data = implode('', $data);
            }

            $data = str_replace(array(
                '  ',
                "\n",
                "\r",
                "\t"
            ), array(
                ' ',
                '',
                '',
                ''
            ), $data);
            return $data;
        }
    }

    public static function generateHash($prefix = '')
    {
        return uniqid($prefix);
    }

    public static function getmicrotime()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    public static function gridFormatFromTree($data, $sequence = null)
    {
        while (list($k, $v) = each($data)) {
            if ($sequence) {
                foreach ($sequence as $seq) {
                    if (($res = $v[$seq]) or ($res = $v['params'][$seq])) {
                        $nv[$seq] = $res;
                    }
                }

                $v = $nv;
            }

            $result['rows'][$k] = array(
                'data' => array_values($v)
            );
        }

        return $result;
    }
}
