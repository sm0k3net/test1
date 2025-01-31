<?php

use Composer\Autoload\ClassLoader;
use X4\Classes\XRegistry;
use MatthiasMullie\Minify;

class X4Autoloader
{
    public static $loader;

    public static function init()
    {
        self::$loader = new ClassLoader();
        self::$loader->setPsr4('X4\\Classes\\', $_SERVER['DOCUMENT_ROOT'] . '/x4/inc/classes');
        self::$loader->setPsr4('X4\\AdminBack\\', $_SERVER['DOCUMENT_ROOT'] . '/x4/adm/back/');
        self::$loader->setPsr4('X4\\Core\\', $_SERVER['DOCUMENT_ROOT'] . '/x4/inc/core/');
        self::$loader->register();
    }


}


class xSingleton
{
    private static $objInstances = array();

    public static function getInstance($className = null)
    {
        if (!$className) {
            $className = get_called_class();
        }

        if (!isset(self::$objInstances[$className])) {
            $instance = new ReflectionClass($className);
            $instance->getConstructor();
            self::$objInstances[$className] = $instance->newInstance();

            // регистрируем в реестре

            XRegistry::set($className, self::$objInstances[$className]);
        }

        return self::$objInstances[$className];
    }

    private function __clone()
    {
    }
}

class xConfig
{
    public static $store = array();
    public static $lastPush = array();

    public static function set($branch, $param, $value)
    {
        self::$store[$branch][$param] = $value;
    }

    public static function pushConfig($arrayValue)
    {
        self::$lastPush = $arrayValue;
    }

    public static function saveLastSetted($key)
    {
        self::setBranch($key, self::$lastPush);
        $data = self::$lastPush;
        self::$lastPush = null;

        return $data;
    }

    public static function addToBranch($branch, $paramset)
    {
        if(is_array($paramset))
        {
            foreach($paramset as $key=>$item){                
                self::$store[$branch][$key] = $item;  
            }
        } 
    }
    
    public static function setBranch($branch, $paramset)
    {
        self::$store[$branch] = $paramset;
    }

    public static function setSubParam($branch, $param, $subParam, $value)
    {
        self::$store[$branch][$param][$subParam] = $value;
    }

    public static function get($branch, $param, $subParam = null)
    {
        if (($subParam) && isset(self::$store[$branch][$param]) && (is_array(self::$store[$branch][$param]))) {
            return self::$store[$branch][$param][$subParam];
        } else {
            return self::$store[$branch][$param];
        }
    }

    public static function getBranch($branch)
    {
        return self::$store[$branch];
    }

    public static function define($name, $value)
    {
        if (!defined($name)){
            define($name, $value);
        }
    }
}

class cssCollector
{
    private static $csslist;
    private static $cachePath='css-compiled';
    
    public static function push($ns, $css, $priority = 10)
    {
        
        self::$csslist[$ns][$priority][] = $css;
    }

    public static function get($ns, $compress = false,$fileName=null)
    {
        
        $css=array();        
        if ($list = self::$csslist[$ns]) {
            ksort($list);
            $klist = array();
            foreach ($list as $larr) {
                $klist = array_merge($klist, $larr);
            }

            foreach ($klist as $item) {
                if (!$compress) {
                    $css[]='<link rel="stylesheet" type="text/css" href="'.$item.'">';        
                } else {
                    $css[] = str_replace(array(CHOST), array(''), $item);
                }
            }

            if (!$compress) {
                return implode($css, "\r\n");
            } else {
                $compressedFile=self::compileToSingleCss($css,$fileName);
                return '<link rel="stylesheet" type="text/css" href="'.$compressedFile.'">';
            }
        }
    }
  
    public static function recompileCssUrl($file){
        
        $cssFileContent=file_get_contents($file);                               
        $fp = fopen($file, "r");      
        $dir=dirname(str_replace(PATH_,'',$file));
        $nline='';
        
        while (!feof($fp)) {
            $line = fgets($fp);                        
            $line =preg_replace_callback('/url\(([\s])?([\"|\'])?(.*?)([\"|\'])?([\s])?\)/i',                      
            function($matches) use ($dir){

                        if(!strstr('data:image',$matches[3])&&!strstr('http://',$matches[3]) )
                        {
                            return 'url("'.$dir.'/'.$matches[3].'")';
                        }else{
                            return  $matches[0];
                        }
                }
                ,$line);
            $nline.=$line;    
            }
        return $nline;
    }
  
    public static function compileToSingleCss($cssList,$fileName=null)
    {     
                
        $marker=Common::createMark($cssList);        
        $modTime=0;
        
        foreach($cssList as $item)
        {
            $itemPath=PATH_.$item;          
              
            $fileTime=filemtime($itemPath);                        
           
            if($modTime<$fileTime)
            {
                 $modTime=$fileTime;
            }
                        
        }
     
        $modTimeFormatted=date('__d-m-y-h-i-s',$modTime);        
                
        if(!$fileName){
                $file=$marker.$modTimeFormatted.'.css';
        }else{
                $file=$fileName.'.css';
        }
                
        Common::loadDriver('xCache', 'xCacheFileDriver');
        
        if(!xCacheFileDriver::fileExists(self::$cachePath,$file))
        {
                 $contents='';
                 foreach($cssList as $item)
                 {
                      $itemPath=PATH_.$item;                                            
                      $contents.="\r\n".self::recompileCssUrl($itemPath);
                      
                      //$contents.="\r\n".file_get_contents($itemPath);                               
                 }    
             
             xCacheFileDriver::writeFile($contents,self::$cachePath,$file);                           
        }
        
        return 'cache/'.self::$cachePath.'/'.$file;
    }
    

    public static function pushCssDir($ns, $dir, $priority = 10)
    {
        if ($files = XFILES::filesList($dir, 'files', array(
            '.css',
        ))) {
            foreach ($files as $file) {
                $file = str_replace(PATH_, HOST, $file);
                self::push($ns, $file, $priority);
            }
        }
    }
}


class jsCollector
{
    private static $jslist;

    public static function get($ns, $compress = false)
    {

        if ($list = self::$jslist[$ns]) {
            ksort($list);
            $klist = array();
            $js=array();
            foreach ($list as $larr) {
                $klist = array_merge($klist, $larr);
            }
            foreach ($klist as $item) {
                if (!$compress or (STATE=='DEVELOP')){
                    $js[] = '<script type="text/javascript" src="' . $item . '"></script>';
                } else {
                    $js[] = str_replace(array(CHOST), array(''), $item);
                }
            }
            
            
                       
            if (!$compress or (STATE=='DEVELOP')){
                return implode($js, "\r\n");
            } else {
                $compressedFile = self::compileToSingleJs($js);
                return '<script type="text/javascript" src="' . $compressedFile . '"></script>';
            }
        }
    }

    public static function compileToSingleJs($jsList)
    {

        $gzip = '';

        $marker = Common::createMark($jsList);
        $modTime = 0;
        foreach ($jsList as $item) {
            $itemPath = PATH_ . $item;

            $fileTime = filemtime($itemPath);

            if ($modTime < $fileTime) {
                $modTime = $fileTime;
            }
        }

        $modTimeFormatted = date('__d-m-y-h-i-s', $modTime);

        if (xConfig::get('GLOBAL', 'outputJsCompress')) $gzip = '__zip';

        $file = $marker . $modTimeFormatted . $gzip . '.js';

        Common::loadDriver('XCache', 'XCacheFileDriver');

             
        $minifier = new Minify\JS();
        
        if (!XCacheFileDriver::fileExists('js-compile', $file)) {
            $contents = '';
            
            foreach ($jsList as $item) {                                
                $minifier->add(PATH_ . $item);
            }

            XCacheFileDriver::createDir('js-compile');

            if (xConfig::get('GLOBAL', 'outputJsCompress')) 
            {
                $minifier->gzip(xConfig::get('PATH', 'CACHE') .'js-compile/'.$file);
            }else{
                
                $minifier->minify(xConfig::get('PATH', 'CACHE') .'js-compile/'.$file);
            }

        }

        return '/cache/js-compile/' . $file;
    }

    public static function pushJsDir($ns, $dir, $priority = 10)
    {
        if ($files = XFILES::filesList($dir, 'files', array(
            '.js',
        ))
        ) {
            foreach ($files as $file) {
                $file = str_replace(PATH_, HOST, $file);
                self::push($ns, $file, $priority);
            }
        }
    }

    /***
     * put your comment there...
     *
     * @param mixed $ns
     * @param mixed $js
     * @param mixed $priority
     */
    public static function push($ns, $js, $priority = 10)
    {
        
        if(is_array(self::$jslist[$ns])){
            foreach (self::$jslist[$ns] as $group){
               if(!empty($group[md5($js)]))return;
            }
        }
        
            self::$jslist[$ns][$priority][md5($js)] = $js;
        
    }
}

class XDATE
{
    public static function rusDateToTimeStamp($date, $separator = '/')
    {
        $dateSep = explode($separator, $date);
        $dta = $dateSep[1] . '/' . $dateSep[0] . '/' . $dateSep[2];

        return strtotime($dta);
    }
}

class xSecureInput
{
    public $total = array();

    public function testArray($array)
    {
        foreach ($array as $name => $value) {
            if (is_array($value) === true) {
                $this->testArray($value);
            } else {
                $this->testHelper($value);
            }
        }
    }

    private function testHelper($varvalue)
    {
        $this->total[$varvalue] = $this->test($varvalue);
    }

    public function test($varvalue, $_comment_loop = false)
    {
        $total = 0;
        $varvalue_orig = $varvalue;
        $quote_pattern = '\%27|\'|\%22|\"|\%60|`';

        //      detect base64 encoding

        if (preg_match('/^[a-zA-Z0-9\/+]*={0,2}$/', $varvalue) > 0 && base64_decode($varvalue) !== false) {
            $varvalue = base64_decode($varvalue);
        }

        //      detect and remove comments

        if (preg_match('!/\*.*?\*/!s', $varvalue) > 0) {
            if ($_comment_loop === false) {
                $total += $this->test($varvalue_orig, true);
                $varvalue = preg_replace('!/\*.*?\*/!s', '', $varvalue);
            } else {
                $varvalue = preg_replace('!/\*.*?\*/!s', ' ', $varvalue);
            }

            $varvalue = preg_replace('/\n\s*\n/', "\n", $varvalue);
        }

        $varvalue = preg_replace('/((\-\-|\#)([^\\n]*))\\n/si', ' ', $varvalue);

        //      detect and replace hex encoding
        //      detect and replace decimal encodings

        if (preg_match_all('/&#x([0-9]{2});/', $varvalue, $matches) > 0 || preg_match_all('/&#([0-9]{2})/', $varvalue, $matches) > 0) {

            //          replace numeric entities

            $varvalue = preg_replace('/&#x([0-9a-f]{2});?/ei', 'chr(hexdec("\\1"))', $varvalue);
            $varvalue = preg_replace('/&#([0-9]{2});?/e', 'chr("\\1")', $varvalue);

            //          replace literal entities

            $trans_tbl = get_html_translation_table(HTML_ENTITIES);
            $trans_tbl = array_flip($trans_tbl);
            $varvalue = strtr($varvalue, $trans_tbl);
        }

        $and_pattern = '(\%41|a|\%61)(\%4e|n|%6e)(\%44|d|\%64)';
        $or_pattern = '(\%6F|o|\%4F)(\%72|r|\%52)';
        $equal_pattern = '(\%3D|=)';
        $regexes = array(
            '/(\-\-|\#|\/\*)\s*$/si',
            '/(' . $quote_pattern . ')?\s*' . $or_pattern . '\s*(\d+)\s*' . $equal_pattern . '\s*\\4\s*/si',
            '/(' . $quote_pattern . ')?\s*' . $or_pattern . '\s*(' . $quote_pattern . ')(\d+)\\4\s*' . $equal_pattern . '\s*\\5\s*/si',
            '/(' . $quote_pattern . ')?\s*' . $or_pattern . '\s*(\d+)\s*' . $equal_pattern . '\s*(' . $quote_pattern . ')\\4\\6?/si',
            '/(' . $quote_pattern . ')?\s*' . $or_pattern . '\s*(' . $quote_pattern . ')?(\d+)\\4?/si',
            '/(' . $quote_pattern . ')?\s*' . $or_pattern . '\s*(' . $quote_pattern . ')([^\\4]*)\\4\\5\s*' . $equal_pattern . '\s*(' . $quote_pattern . ')/si',
            '/(((' . $quote_pattern . ')\s*)|\s+)' . $or_pattern . '\s+([a-z_]+)/si',
            '/(' . $quote_pattern . ')?\s*' . $or_pattern . '\s+([a-z_]+)\s*' . $equal_pattern . '\s*(d+)/si',
            '/(' . $quote_pattern . ')?\s*' . $or_pattern . '\s+([a-z_]+)\s*' . $equal_pattern . '\s*(' . $quote_pattern . ')/si',
            '/(' . $quote_pattern . ')?\s*' . $or_pattern . '\s*(' . $quote_pattern . ')([^\\4]+)\\4\s*' . $equal_pattern . '\s*([a-z_]+)/si',
            '/(' . $quote_pattern . ')?\s*' . $or_pattern . '\s*(' . $quote_pattern . ')([^\\4]+)\\4\s*' . $equal_pattern . '\s*(' . $quote_pattern . ')/si',
            '/(' . $quote_pattern . ')?\s*\)\s*' . $or_pattern . '\s*\(\s*(' . $quote_pattern . ')([^\\4]+)\\4\s*' . $equal_pattern . '\s*(' . $quote_pattern . ')/si',
            '/(' . $quote_pattern . '|\d)?(;|%20|\s)*(union|select|insert|update|delete|drop|alter|create|show|truncate|load_file|exec|concat|benchmark)((\s+)|\s*\()/ix',
            '/from(\s*)information_schema.tables/ix',
        );
        foreach ($regexes as $regex) {
            $total += preg_match($regex, $varvalue);
        }

        return $total;
    }
}
