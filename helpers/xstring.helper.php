<?php
class XSTRING
{
    public static function trimall($str, $charlist = " \n\r")
    {
        return str_replace(str_split($charlist), '', $str);
    }

    public static  function is_string_int($int)
    {
        if (is_numeric($int) === TRUE)
        {
            if ((int) $int == $int)
            {
                return true;
            }
            else
            {
                return false;
            }
        }
        else
        {
            return false;
        }
    }

    public static  function declination($number, $titles)
    {
        $cases = array(
            2,
            0,
            1,
            1,
            1,
            2
        );
        return $titles[($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)]];
    }

    public static  function dateRecognize($date)
    {
        $matched=false;
        if(is_string($date)){
            $matched=preg_match('/^\s*(\d\d?)[^\w](\d\d?)[^\w](\d{1,4}\s*$)/', $date, $match);
        }

        if ($matched)
        {
            return strtotime($date);
        }
        else
        {
            return $date;
        }
    }

    public static  function Reg($word)
    {
        $word     = preg_replace("/(\.|\-|\_)/", "", strtolower($word));
        $patterns = file('censor.bws');
        for ($i = 0; $i < count($patterns); $i++)
        {
            $patterns[$i] = trim($patterns[$i]);
            if (preg_match($patterns[$i], $word))
                return true;
        }
        
        return false;
    }

     public static   function censorfilter($string)
    {
        $string    = trim($string);
        $str_words = explode(' ', $string);
        for ($i = 0; $i < count($str_words); $i++)
        {
            if (self::Reg($str_words[$i]))
            {
                $str_words[$i] = ' <font color=red>[censored]</font> ';
            }
        }
        
        return $string = implode(' ', $str_words);
    }

     public static function findnCutSymbolPosition($str, $symbol, $maxposition, $end = '') {
        
        $offset = 0;
        $str    = trim($str);
        $l      = strlen($str);

        if ($l <= $maxposition) {
            return $str;
        } else {
            $pos    = strpos($str, $symbol);
            
            if(!$pos)return $str;
            
            if($pos>$maxposition)
            {
                 $offset=$pos;
                    
            }else{
            
                while(($pos <= $maxposition)&&($pos!==false)){
                    $offset = $pos + 1;
                    $pos    = strpos($str, $symbol, $offset);
                }

            }
            $resStr = substr($str, 0, $offset);

            return $resStr.$end;
        }
    }
    
    
    
    
    public static function Words2AllForms($text)
    {
          require_once(xConfig::get('PATH', 'EXT'). 'phpMorphy/src/common.php');

        $opts=array
        (
            //            PHPMORPHY_STORAGE_FILE - использует файловые операции (fread, fseek) для доступа к словарям
            //            PHPMORPHY_STORAGE_SHM - загружает словари в общую память (используя расширение PHP shmop)
            //            PHPMORPHY_STORAGE_MEM - загружает словари в память
            'storage'           => PHPMORPHY_STORAGE_MEM,
            //            Extend graminfo for getAllFormsWithGramInfo method call
            'with_gramtab'      => false,
            'predict_by_suffix' => true,
            'predict_by_db'     => true
        );

        $encoding = 'utf8';
        $dir = xConfig::get('PATH', 'EXT'). 'phpMorphy/dicts/';
             
        //        Создаем объект словаря
        $dict_bundle=new phpMorphy_FilesBundle($dir, 'rus');
        $morphy     =new phpMorphy($dict_bundle, $opts);

        //        $codepage = $morphy->getCodepage();
        setlocale(LC_CTYPE, array
        (
            'ru_RU.CP1251',
            'Russian_Russia.1251'
        ));

        $words=preg_split('#\s|[,.:;!?"\'()]#', $text, -1, PREG_SPLIT_NO_EMPTY);

        $bulkWords=array();

        

        foreach ($words as $v)
        {
            if (strlen($v) > 3)
            {
                $v           =iconv("UTF-8", "windows-1251", $v);
                $bulkWords[]=strtoupper($v);
            }
        }

        return $morphy->getAllForms($bulkWords);
    }
    
     public static  function Words2BaseForm($text)
     {
         
         static $dictBundle, $morphy;
               
              
         require_once(xConfig::get('PATH', 'EXT'). 'phpMorphy/src/common.php');
          
         if(!$dictBundle)
         {
          
             $encoding = 'utf8';
             $dir = xConfig::get('PATH', 'EXT'). 'phpMorphy/dicts/';
             $dictBundle = new phpMorphy_FilesBundle($dir, 'rus');
         }
         
         if(!$morphy)
         {
             $opts = array(
                 'storage'           => PHPMORPHY_STORAGE_MEM,
                 'with_gramtab'      => false,
                 'predict_by_suffix' => true, 
                 'predict_by_db'     => true
             );
       
             $morphy = new phpMorphy($dictBundle, $opts);
         }
     
     
         setlocale(LC_CTYPE, array('ru_RU.CP1251', 'rus_RUS.CP1251', 'rus_RUS.CP1251', 'Russian_Russia.1251'));
     
         $words = preg_replace('#\[.*\]#isU', '', $text);
         $words = preg_split('#\s|[,.:;В«В»!?"\'()]#', $words, -1, PREG_SPLIT_NO_EMPTY);
     
         $bulkWords = array();
         
         foreach($words as $v)
         {
             if (strlen($v) > 3)
             {
                 $bulkWords[] = strtoupper($v);
             }
         }
         
         $baseForm = $morphy->getBaseForm($bulkWords);
         $fullList = array();
 
         if(is_array($baseForm) && count($baseForm))
         {
             foreach($baseForm as $k => $v)
             {
                 if(is_array($v))
                 {
                     foreach($v as $v1)
                     {
                         if(strlen($v1) > 3)
                         {
                             $fullList[$v1] = 1;    
                         }    
                     }    
                 }    
             }
         }
         
         $words = join(' ', array_keys($fullList));
         
         return $words;
     }
    

}
