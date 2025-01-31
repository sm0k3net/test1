<?php
require_once __DIR__ . '/x4/vendor/autoload.php';
require($_SERVER['DOCUMENT_ROOT'].'/x4/inc/core/helpers.php');
require($_SERVER['DOCUMENT_ROOT'].'/x4/inc/helpers/common.helper.php');
X4Autoloader::init();

require("conf/init.php");

        function gzipOutput($input)
        {    
           $expires=86000;
           $all=file_get_contents(PATH_.$input);     
           if (xConfig::get('GLOBAL','outputJsCompress'))
            {                
            if (@$_SERVER["HTTP_ACCEPT_ENCODING"] && FALSE !== strpos($_SERVER["HTTP_ACCEPT_ENCODING"], 'gzip'))
                {                     
                    header("Content-type: application/x-javascript");
                    header ('Content-Encoding: gzip');            
                    header ('Content-Length: ' . strlen($all));
                    header('Cache-Control: max-age='.$expires.', must-revalidate');
                    header('Pragma: public');
                    header('Expires: '. gmdate('D, d M Y H:i:s', time()+$expires).'GMT');
                }
            }    
             echo $all;
        }

if (isset($_GET['m'])){gzipOutput($_GET['m']);}