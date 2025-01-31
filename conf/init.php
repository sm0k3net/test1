<?php

define('DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT']);
define('HTTP_HOST', $_SERVER['HTTP_HOST']);

include($_SERVER['DOCUMENT_ROOT'] . '/conf/db.init.php');

/** Environment overrides */
if (is_file($_SERVER['DOCUMENT_ROOT'].'/conf/dev.php')){
    include($_SERVER['DOCUMENT_ROOT'].'/conf/dev.php');
}


/**GLOBAL CONSTANT INIT**/

define('PROTOCOL', 'http');
define('HOST', PROTOCOL . '://' . HTTP_HOST . '/');
define('CHOST', PROTOCOL . '://' . HTTP_HOST);
define('PATH_', DOCUMENT_ROOT . '/');
define('X4_VERSION', '1.6 SUN');
define('DEFAULT_CHUNK_SIZE', 100);

//define('STATE', 'DEVELOP'); 

xConfig::define('STATE', 'PRODUCT');

//define('RENDERMODE', 'HEADLESS');

define('RENDERMODE', 'NORMAL');

/** DATABASE DATA **/

xConfig::setBranch('DB', $DB_CONNECTION_DATA[STATE]);

/** GLOBAL PATHS INIT **/

xConfig::addToBranch('PATH',
    array(
        'INC' => PATH_ . 'x4/inc/',
        'PROJECT' => PATH_ . 'project/',
        'MEDIA' => PATH_ . 'media/',
        'MODULES' => PATH_ . 'x4/modules/',
        'BASE' => PATH_ . 'x4/front-base/',
        'PLUGINS' => PATH_ . 'project/plugins/',
        'CACHE' => PATH_ . 'cache/',
        'TPL_CACHE' => PATH_ . 'cache/tpl/',
        'TEMPLATES' => PATH_ . 'project/templates/',
        'CACHE' => PATH_ . 'cache/',
        'ADM' => PATH_ . 'x4/adm/',
        'ADMBACK' => PATH_ . 'x4/adm/back/',
    ));

xConfig::addToBranch('PATH',
    array(
        'CORE' => xConfig::get('PATH', 'INC') . 'core/',
        'CLASSES' => xConfig::get('PATH', 'INC') . 'classes/',
        'DRIVERS' => xConfig::get('PATH', 'INC') . 'drivers/',
        'EXT' => xConfig::get('PATH', 'INC') . 'ext/',
        'XJS' => xConfig::get('PATH', 'ADM') . 'xjs/',
        'EXPORT' => xConfig::get('PATH', 'MEDIA') . 'export/',
        'BACKUP' => xConfig::get('PATH', 'MEDIA') . 'backup/'));


xConfig::addToBranch('PATH',
    array(
        'XOAD' => xConfig::get('PATH', 'EXT') . 'xoad/',
        'COMMON_TEMPLATES' => xConfig::get('PATH', 'TEMPLATES') . '_common/',
        'SITEMAP' => 'sitemap.xml',
        'MODULES_TEMPLATES' => xConfig::get('PATH', 'TEMPLATES') . '_modules/'
    ));


xConfig::addToBranch('WEBPATH',
    array(
        'MEDIA' => HOST . 'media/',
    ));

xConfig::addToBranch('WEBPATH',
    array(
        'EXPORT' => xConfig::get('WEBPATH', 'MEDIA') . 'export/',
        'ARES' => HOST . 'project/templates/_ares/',
        'BASE' => HOST . 'x4/front-base/',
        'EXT' => HOST . 'x4/inc/ext/',
        'XJS' => HOST . 'x4/adm/xjs/',
        'XOAD' => HOST . 'x4/inc/ext/xoad'
    ));

	xConfig::addToBranch('DOMAINSYNONYMS',
    array(
        'www.sanline.by'=>'sanline.by'
    ));
	

xConfig::addToBranch('GLOBAL',
    array(

        'DOMAIN' => HTTP_HOST,
        'HOST' => HOST,
        'CHOST' => CHOST,
        'paginatorParseRadius'=>10,
        'paginatorDefaultChunkSize'=>20,
        'admin_email' => 'info@x4.by',
        'siteEncoding' => 'utf-8',
        'outputJsCompress' => false,
        'outputHtmlCompress' => false,        
        'from_email' => 'shop@x4.by',
        'templateDebug' => false,
        'apiAuthEnabled' => false,
        'apiBasicAuthLogin' => 'x4',
        'apiBasicAuthPassword' => 'x4passAsDefault666',

        'memcacheHost' => 'localhost',
        'memcachePort' => 11211,

        'RedisHost' => '127.0.0.1',
        'RedisPort' => 6379,
        'RedisDBIndex' => 0,


        'showDebugInfo' => true,
        'enableJsonDebugging' => false,
        'compressXoadRequests' => true,
        'treeCacheTimeout'=>86000,
        'cacheTimeout' => 86000,
        'actionModuleCache' => true,
        'defaultLanguage' => 'rus',
        'enableLogs' => true,
        'enableBrowserConsoleLogs' => true,
        'enablePerfomanceMonitor' => false,
        'enableBackLogs' => true,

        'defaultModeFiles' => 0755,
        'outputJsBackCompress' => 1,
        'fileImagesExt' => array('.jpg', '.png', '.gif', '.JPG', '.PNG', '.GIF'),
        'allowedFileUploadExt' => array('jpg', 'gif', 'pdf', 'docx', 'doc', 'xlsx', 'html', 'htm', 'zip', 'txt', 'png', 'pdf', 'swf', 'xls', 'doc', 'JPG', 'PNG', 'PDF', 'DOC', 'GIF'),

    ));

X4\Classes\XCache::initialize('File');
