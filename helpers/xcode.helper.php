<?php
class XCODE
{
  static  function encode_entities($s)
    {
        static $trans;
        if (!is_array($trans))
        {
            $trans = get_html_translation_table(HTML_ENTITIES);
            $trans = array_slice($trans, 0, 27);
        }
        
        if (is_array($s))
        {
            XARRAY::arrayWalkRecursive2($s, 'entities_recursive');
            return $s;
        }
        
        $s = str_replace(array(
            'Ё',
            'ё'
        ), array(
            '&#203;',
            '&#235;'
        ), $s);
        return strtr($s, $trans);
    }
    
  static function utf2win($str, $force = false)
    {
        global $_COMMON_SITE_CONF;
        static $Encoding;
        if (($_COMMON_SITE_CONF['siteEncoding'] == 'utf-8') && (!$force))
            return $str;
        if (is_array($str))
        {
            $str    = implode('@@', $str);
            $a_flag = 1;
        }
        
        if (!$force)
        {
            $force = $_COMMON_SITE_CONF['siteEncoding'];
        }
        
        if (!$Encoding)
        {
            $Encoding = new ConvertCharset("utf-8", $force, $Entities);
        }
        
        if (!$m = $Encoding->Convert($str))
        {
            $m = $str;
        }
        
        if ($a_flag)
        {
            $m = explode('@@', $m);
        }
        
        return $m;
    }
    
  static  function translit($text, $dont_strip_tags = false, $dont_clean_specialchars = false)
    {
        if (!$dont_strip_tags)
            $text = strip_tags($text);
        $filter = array(
            "А" => "A",
            "а" => "a",
            "Б" => "B",
            "б" => "b",
            "В" => "V",
            "в" => "v",
            "Г" => "G",
            "г" => "g",
            "Д" => "D",
            "д" => "d",
            "Е" => "E",
            "е" => "e",
            "Ё" => "Yo",
            "ё" => "yo",
            "Ж" => "J",
            "ж" => "j",
            "З" => "Z",
            "з" => "z",
            "И" => "I",
            "и" => "i",
            "Й" => "I",
            "й" => "i",
            "К" => "K",
            "к" => "k",
            "Л" => "L",
            "л" => "l",
            "М" => "M",
            "м" => "m",
            "Н" => "N",
            "н" => "n",
            "О" => "O",
            "о" => "o",
            "П" => "P",
            "п" => "p",
            "Р" => "R",
            "р" => "r",
            "С" => "S",
            "с" => "s",
            "Т" => "T",
            "т" => "t",
            "У" => "U",
            "у" => "u",
			"ў" => "u",
            "Ф" => "F",
            "ф" => "f",
            "Х" => "h",
            "х" => "h",
            "Ц" => "Z",
            "ц" => "z",
            "Ч" => "Ch",
            "ч" => "ch",
            "Ш" => "Sh",
            "ш" => "sh",
            "Щ" => "Sch",
            "щ" => "sch",
            "Э" => "E",
            "э" => "e",
            "Ю" => "Yu",
            "ю" => "yu",
            "Я" => "Ya",
            "я" => "ya",
            "Ь" => "",
            "ь" => "",
            "Ъ" => "",
            "ъ" => "",
            "Ы" => "I",
            "ы" => "i",
            " " => "-"
        );
        if (!$dont_clean_specialchars)
            $filter += array(
                '"' => '',
                "'" => "",
                "+" => "_plus_",
                "!" => "",
                "?" => "",
                '`' => '',
                '*' => '',
                '#' => '',
                '%' => '',
                '^' => '',
                ',' => '-'
            );
        return strtr($text, $filter);
    }
    
    
    
        static  function jsonDecode($string,$assoc=null)
             {
        
               $value=json_decode($string,$assoc);
               $error=json_last_error();
               if($error!=JSON_ERROR_NONE)
               {
                   
                    throw new Exception('json-format-error '. $string);
               }
               
               return $value;
               
             }
            
            
            
    static function urldecodeArray(&$var)
    {
        if(is_array($var))
        {
                foreach ($var as $key=>$value)
                {
                    if(is_array($value))
                    { 
                                    $value=XCODE::urldecodeArray($value);
                    } else {
                                    $value=urldecode($value);
                    }
                            $var[$key]=$value;
                }
                return $var;
        }
    }
        
            
    static function win2utf($s, $force = false)
    {
        global $_COMMON_SITE_CONF;
        static $Encoding;
        if (($_COMMON_SITE_CONF['siteEncoding'] == 'utf-8') && (!$force))
            return $s;
        if (!$force)
        {
            $force = $_COMMON_SITE_CONF['siteEncoding'];
        }
        
        if (!is_object($Encoding))
        {
            $Encoding = new ConvertCharset($force, "utf-8", $Entities);
        }
        
        if (is_array($s))
        {
            XARRAY::arrayWalkRecursive2($s, 'winutf_recursive', $force);
            return $s;
        }
        
        if (!$m = $Encoding->Convert($s))
        {
            $m = $str;
        }
        
        return $m;
    }
}
?>
