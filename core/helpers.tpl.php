<?php

use X4\Classes\XNameSpaceHolder;
use X4\Classes\XRegistry;
use X4\Classes\MultiSection;
use X4\Classes\XCache;
use X4\Classes\ImageRendererUtils;

class DEBUG
{
    public $debugnext = false;

    public function debugger()
    {
        debugbreak();
    }

    public function debugNext()
    {
        $this->debugnext = true;
    }

    private function lazyLoadDebug()
    {
        static  $loaded;

        if (!$loaded) {
            require_once xConfig::get('PATH', 'EXT').'ref/ref.php';
            ref::config('showUrls', false);
            ref::config('showBacktrace', false);
            $loaded = true;
        }
    }

    public function __construct()
    {
    }

    public function var_dump($var)
    {
        $output = var_export($var, true);
        $output = preg_replace("/\]\=\>\n(\s+)/m", '] => ', $output);
        $output = '<pre>'.$label.htmlspecialchars($output, ENT_QUOTES).'</pre>';

        return $output;
    }
    
    
    

    private static function escapeJsonString($value) {
    # list from www.json.org: (\b backspace, \f formfeed)    
    $escapers =     array("\\",     "/",   "\"",  "\n",  "\r",  "\t", "\x08", "\x0c");
    $replacements = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t",  "\\f",  "\\b");
    $result = str_replace($escapers, $replacements, $value);
    return $result;
  }
  
    public function consoleLog($params,$section)
    {    
        $json=json_encode($params['value'],JSON_HEX_TAG);
        $json=self::escapeJsonString($json);
        return '<script>console.info("section:'.$section['section'].'");console.info(JSON.parse(\''.$json.'\'))</script>';        
    }

    public function dumpVal($params, $section)
    {
        $this->lazyLoadDebug();

        return  r($section['value']);
    }

    public function dump($params)
    {
        $this->lazyLoadDebug();

        return  r($params['value']);
    }

    public function getVars($params, $section)
    {
        $TMS = $section['TMS'];

        $this->lazyLoadDebug();
        if ($TMS->potentialKeys[$section['section']]) {
            $sr = array_merge($TMS->getSectionReplacements($section['section']), array_keys($TMS->potentialKeys[$section['section']]));
            if ($fr = array_merge($TMS->Fields[$section['section']], $TMS->potentialKeys[$section['section']])) {
                foreach ($sr as $key => $srElement) {
                    if (strstr($srElement, '33_each') or (strpos($srElement, '#') === 0) or (strpos($srElement, '@') === 0)) {
                        unset($fr[$key]);
                        unset($sr[$key]);
                    }
                }
            }
        } else {
            $sr = $TMS->getSectionReplacements($section['section']);
            $fr = $TMS->Fields[$section['section']];
        }

        $res = array_combine($sr, $fr);

        return  @r($res);
    }
}

class ENHANCE
{
    public $e = 0;

    public function declination($params)
    {
        return XSTRING::declination($params['num'], $params['declination']);
    }

    public function inUrl($params)
    {
        $bu = xConfig::get('PATH', 'baseUrl');
        if (strstr($bu, $params['in'])) {
            return 1;
        } else {
            return 0;
        }
    }

    public function renderModule($params)
    {
        $params['params']['_Action'] = $params['params']['Action'];
        unset($params['params']['Action']);

        return XRegistry::get('TPA')->executeModuleAction($params['module'], $params['params']);
    }

    public function setPageNavMove($params)
    {
        Common::$pageMoveChunk = $params['pages'];
        Common::$parseRadius = $params['pages'];
    }

    public function addFileSection($params)
    {
        XRegistry::get('TMS')->addFileSection(xConfig::get('PATH', 'TEMPLATES').$params['file']);
    }

    public function cut_words($params)
    {
        if (!$params[1]) {
            $params[1] = ' ';
        }
        if (isset($params[3])) {
            return XSTRING::findnCutSymbolPosition($params[0], $params[1], $params[2], $params[3]);
        }

        return XSTRING::findnCutSymbolPosition($params[0], $params[1], $params[2]);
    }

    public function cut_words2($params)
    {
        if (!$params[5]) {
            $str = (strip_tags(iconv('UTF-8', 'WINDOWS-1251', $params[0])));
        }
        if (strlen($str) <= $params[2]) {
            return stripslashes($params[0]);
        }
        if (!$params[1]) {
            $params[1] = ' ';
        }
        if (!$params[4]) {
            $params[4] = '...';
        }
        $tmp = substr($str, 0, $params[2]);
        $pos = strpos($str, $params[1], $params[2]);
        if ($pos !== false) {
            $tmp .= substr($str, $params[2], $pos - $params[2]).$params[4];
        } else {
            return stripslashes($params[0]);
        }
        if (!$params[5]) {
            $tmp = iconv('WINDOWS-1251', 'UTF-8', $tmp);
        }

        return stripslashes($tmp);
    }

    public function callModule($params)
    {
        Common::moduleFactory($params['module']);
    }

    public function callModuleAction($params)
    {
        static $i;
        ++$i;
        $params['params']['_Type'] = $params['module'];
        if ($result = XRegistry::get('TPA')->executeModuleAction($params['module'], $params['params'], 'CMA'.$i)) {
            return $result;
        } else {
            return false;
        }
    }

    public function enableCssFile($params)
    {
        if (!isset($params['ns'])) {
            $params['ns'] = 'main';
        }
        if (!isset($params['priority'])) {
            $params['priority'] = 5;
        }

        if (isset($params['customFile'])) {
            cssCollector::push($params['ns'], HOST.$params['customFile'], $params['priority']);
        } else {
            cssCollector::push($params['ns'], xConfig::get('PATH', 'WEB_ARES').$params['file'], $params['priority']);
        }
    }
	
    public function enableJsFile($params)
    {
        if (!isset($params['ns'])) {
            $params['ns'] = 'main';
        }
        if (!isset($params['priority'])) {
            $params['priority'] = 5;
        }

        if (isset($params['customFile'])) {
            jsCollector::push($params['ns'], HOST.$params['customFile'], $params['priority']);
        } else {
            jsCollector::push($params['ns'], xConfig::get('WEBPATH', 'ARES').$params['file'], $params['priority']);
        }
    }
    
    public function getJsCollection($params)
    {
       
        if (!isset($params['ns'])) 
        {
            $params['ns'] = 'main';
        }

        if (!isset($params['compress'])) 
        {
            $compress=false;
            
        }else{            
            
            $compress=$params['compress'];            
        }
                        
                
        return jsCollector::get($params['ns'],$compress);
    }

    public function fileExists($params)
    {
        if ($params['file'] && file_exists(PATH_.$params['file'])) {
            return true;
        } else {
            return false;
        }
    }

    /*
    Пример вызова
    {%F:v>image(E:imageTransform({"r":{"w":"108","h":"65","c":"108:65"},"s":{"a":"80"}}))%}
    Описание параметров -- в ImageRendererUtils.php
    */
    
    
  public function imageTransform($settings, $section)
    {
        static $loaded=false;

        if($settings[0]) $settings = $settings[0];
        
        $imgPath=$section['value'];
     
        if($settings['w'])
        {
            $md = md5($imgPath . print_r($settings, true));
            $_SESSION['imagecachedata'][$md] = $settings;
            $_SESSION['imagecachedata'][$md]['filename'] = $imgPath;
            unset($settings['w']);
            return '/imagerender.php?imghash=' . $md;
        }else{
          
            
            $imageName = ImageRendererUtils::arrayToImageName($settings, $imgPath);
            $wpath      = HOST . 'cache/imagecache/' . $imageName;
            $fpath      = xConfig::get('PATH','CACHE') . 'imagecache/' . $imageName;
            
            
            if (file_exists($fpath) && (filemtime(PATH_ . $imgPath) > filemtime($fpath))){
                unlink($fpath);
            }else{
              
                return $wpath;
            }
            //return '/imagerender.php?settings=' . $image_name;
        }
    }    

    public function translit($params)
    {
        return XCODE::translit($params['value']);
    }

    public function getPicturesFromFolder($params)
    {
        $types = xConfig::get('GLOBAL', 'fileImagesExt');

        if (!isset($params['folder']) or (!$params['folder'])) {
            return false;
        }

        if (isset($params['types'])) {
            $types = $params['types'];
        }

        $params['folder'] = str_replace('articles', 'article', $params['folder']);

        if ($files = XFILES::filesList(PATH_.$params['folder'], 'files', $types, 0, true)) {
            if ($params['sort']) {
                switch ($params['sort']) {
                        case 'natsort':
                            natsort($files);
                            break;
                        case 'rsort':
                            rsort($files);
                            break;
                    }
            }
            $i = 0;

            foreach ($files as $file) {
                ++$i;

                $ext[$i] = array(
                        'image' => $params['folder'].$file,
                    );

                if ($params['getIPTC']) {
                    include_once xConfig::get('PATH', 'EXT').'iptc/iptc.php';
                    $iptc = new Iptc($params['folder'].$file);
                    $ext[$i]['IPTC']['description'] = $iptc->fetch(Iptc::CAPTION) ? base64_decode($iptc->fetch(Iptc::CAPTION)) : '';
                    $ext[$i]['IPTC']['name'] = $iptc->fetch(Iptc::OBJECT_NAME) ? base64_decode($iptc->fetch(Iptc::OBJECT_NAME)) : '';
                    $ext[$i]['IPTC']['disable'] = $iptc->fetch(Iptc::EDIT_STATUS) ? $iptc->fetch(Iptc::EDIT_STATUS) : '';
                    $ext[$i]['IPTC']['ownerEmail'] = $iptc->fetch(Iptc::CAPTION_WRITER) ? $iptc->fetch(Iptc::CAPTION_WRITER) : '';
                }
            }

            return $ext;
        } else {
            return false;
        }
    }

    public function parseXLSFileToStyledTable($params)
    {
        $reader = Common::getLegacyXLSParser($params['file']);

        return $reader->dump(false, false, 0, $params['class']);
    }

    public function parseXLSFileToTable($params)
    {
        
        $path = PATH_.$params['file'];

        if (file_exists($path)&&is_file($path)) {
            $reader = Common::getXLSParser($path);
            $reader->ChangeSheet(0);
            $arrayStructure = array();
            foreach ($reader as $key => $row) {
                $arrayStructure[$key] = $row;
            }

            return XHTML::arrayToTable($arrayStructure);
        } else {
            return false;
        }
    }

    public function serverVar($params)
    {
        return $_SERVER[$params['value']];
    }

    public function str_replace($params)
    {
        if (@$params = current($params)) {
            return str_replace($params['search'], $params['replace'], $params['subject']);
        }
    }

    public function str_repeat($params)
    {
        return str_repeat($params[0], $params[1]);
    }

    public function round($params)
    {
        return round($params['value'], $params['precision']);
    }

    public function numberFormat($params, $section)
    {
        if ($section['value'] && !empty($params)) {
            return number_format($section['value'], $params['decimals'], $params['decpoint'], $params['thousands']);
        } else {
            return number_format($section['value'], 0, ' ', ' ');
        }
    }

    public function dateRu($params, $context)
    {
        static $q;

        $formatum = $params['format'];

        $timestamp = (int) $context['value'];
        if (($timestamp <= -1) || !is_numeric($timestamp)) {
            return '';
        }

        //$l      = Common::get_module_lang('core', $_COMMON_SITE_CONF['site_language'], 'date_format');
        if (!$q) {
            $q['q'] = array(-1 => 'w', 'воскресенье', 'понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота');
            $q['Q'] = array(-1 => 'w', 'Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота');
            $q['v'] = array(-1 => 'w', 'вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб');
            $q['V'] = array(-1 => 'w',  'Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб');
            $q['H'] = array(-1 => 'n', '', 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь');
            $q['x'] = array(-1 => 'n', '', 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря');
            $q['X'] = array(-1 => 'n', '', 'Января', 'Февраля', 'Март', 'Апреля', 'Май', 'Июня', 'Июля', 'Август', 'Сентября', 'Октября', 'Ноября', 'Декабря');
            $q['f'] = array(-1 => 'n', '', 'янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек');
            $q['F'] = array(-1 => 'n', '',  'Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек');
        }

        if ($timestamp == 0) {
            $timestamp = time();
        }
        $temp = '';
        $i = 0;
        while ((strpos($formatum, 'q', $i) !== false) || (strpos($formatum, 'Q', $i) !== false) || (strpos($formatum, 'v', $i) !== false) || (strpos($formatum, 'V', $i) !== false) || (strpos($formatum, 'x', $i) !== false) || (strpos($formatum, 'X', $i) !== false) || (strpos($formatum, 'f', $i) !== false) || (strpos($formatum, 'F', $i) !== false) || (strpos($formatum, 'H', $i) !== false)) {
            $ch['q'] = strpos($formatum, 'q', $i);
            $ch['Q'] = strpos($formatum, 'Q', $i);
            $ch['v'] = strpos($formatum, 'v', $i);
            $ch['V'] = strpos($formatum, 'V', $i);
            $ch['H'] = strpos($formatum, 'H', $i);
            $ch['x'] = strpos($formatum, 'x', $i);
            $ch['X'] = strpos($formatum, 'X', $i);
            $ch['f'] = strpos($formatum, 'f', $i);
            $ch['F'] = strpos($formatum, 'F', $i);
            foreach ($ch as $k => $v) {
                if ($v === false) {
                    unset($ch[$k]);
                }
            }
            $a = min($ch);
            $temp .= date(substr($formatum, $i, $a - $i), $timestamp).$q[$formatum[$a]][date($q[$formatum[$a]][-1], $timestamp)];
            $i = $a + 1;
        }
        $temp .= date(substr($formatum, $i), $timestamp);

        return $temp;
    }
    public function asort($params)
    {
        asort($params[0]);

        return $params[0];
    }
    public function rsort($params)
    {
        rsort($params[0]);

        return $params[0];
    }
    public function calc($params)
    {
        if(!empty($params['eval'])) {
            @eval('$r=' . $params['eval'] . ';');
            return $r;
        }
    }
    public function rand($params)
    {
        return rand();
    }
    public function current($params)
    {
        if (is_array($params[0])) {
            return current($params[0]);
        }
    }


    public function dateFormat($params)
    {
        if ($params['fromTimestamp']) {
            $params['value'] = strtotime($params['value']);
        }

        return date($params['format'], $params['value']);
    }

    public function count($params)
    {
        if ($params['value']) {
            return count($params['value']);
        }
    }

    public function assign($params)
    {
        return $params['value'];
    }

    public function getSession($params)
    {
        return $_SESSION;
    }

    public function getRequestAction()
    {
        return XRegistry::get('TPA')->requestAction;
    }

    public function getRequest($params)
    {
        if ($params['key']) {
            return $_REQUEST[$params['key']];
        } else {
            return $_REQUEST;
        }
    }

    public function checkAuth($params)
    {
        if ($_SESSION['siteuser']['authorized']) {
            return 1;
        }

        return 0;
    }
}

class LOCALIZE{

    public function getCurrentLang($params)
    {
        $lang=Common::getFrontLang($params['lang']);
        if(!empty($params['asJson']))
        {
            return json_encode($lang);

        }else{

            return $lang;
        }
    }

}
