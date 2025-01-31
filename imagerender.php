<?php
use X4\Classes\ImageRenderer;
use X4\Classes\ImageRenererUtils;

error_reporting(0);
require_once($_SERVER['DOCUMENT_ROOT'] . '/x4/vendor/autoload.php');
require($_SERVER['DOCUMENT_ROOT'] . '/x4/inc/core/helpers.php');
require($_SERVER['DOCUMENT_ROOT'] . '/x4/inc/helpers/common.helper.php');
X4Autoloader::init();
require($_SERVER['DOCUMENT_ROOT'] . '/conf/init.php');
require($_SERVER['DOCUMENT_ROOT'] . '/x4/inc/classes/ImageRendererUtils.php');
session_start();


/*
$_SESSION['imagecachedata'][$_GET['imghash']]['w']['i']   = '/media/2011_AZERA_undefined_1313736288173.png';
$_SESSION['imagecachedata'][$_GET['imghash']]['w']['t']   = 'PAVE';
$_SESSION['imagecachedata'][$_GET['imghash']]['w']['p']   = 'RIGHT_BOTTOM';
$_SESSION['imagecachedata'][$_GET['imghash']]['filename'] = '/media/banners/cubebox/img_ru_main_2ndslide.png';*/

if ($_GET['settings']) $_GET['settings'] = basename($_GET['settings']);
if (!$_GET['imghash'] && !$_GET['settings']) ImageRendererUtils::dropError('HTTP/1.1 400 Bad Request', 'Error: not enough $_GET params');
if ($_GET['imghash'] && !isset($_SESSION['imagecachedata'][$_GET['imghash']])) ImageRendererUtils::dropError('HTTP/1.1 400 Bad Request', 'Error: cachedata array does not exists');


if ($settArr = explode('--', $_GET['settings'])) {
    if (isset($_GET['imghash']) && isset($_SESSION['imagecachedata'][$_GET['imghash']]['w'])) {
        $settArr[] = 'wFLAG';
    }

    $settings = array();
    foreach ($settArr as $setting) {
        if (substr($setting, 0, 4) == 'file') {
            $fileData = str_replace('~', '-', (str_replace('-', '/', substr($setting, 4))));
        } else {
            $transformation = $setting{0};
            $param = $setting{1};
            $data = substr($setting, 2);
            if (strpos($data, '-')) $data = explode('-', $data);
            $settings[$transformation][$param] = $data;
            if (isset($_SESSION['imagecachedata'][$_GET['imghash']][$transformation]))
                $settings[$transformation] = array_merge(
                    (array)$_SESSION['imagecachedata'][$_GET['imghash']][$transformation],
                    (array)$settings[$transformation]);
        }
    }
}
if (!$fileData && $_SESSION['imagecachedata'][$_GET['imghash']]['filename'])
    $fileData = $_SESSION['imagecachedata'][$_GET['imghash']]['filename'];

if (isset($_SESSION['imagecachedata'][$_GET['imghash']]))
    $settings = $_SESSION['imagecachedata'][$_GET['imghash']];
$image = new ImageRenderer($fileData, $settings, $_GET['imghash']);




/*
array(
 'r' => array('w'=>'100', 'h'=>'200', 'c'=>'1'),
 'w' => array('w'=>'qweqweqwe', 'a'=>'adsdasd')
)*/
// http://hyundai2.bi/ImageRenderer.php?settings=rw600--rh600--rr52--sa80--fb100--fn1--fs1--fc1-2-4-6--wtPAVE--file-media-banners-cubebox-img_ru_main_2ndslide.png
