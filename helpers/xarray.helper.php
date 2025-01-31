<?php
class XARRAY
{
    public static function arrayMultiSort($array, $cols)
    {
        $colarr = array();
        foreach ($cols as $col => $order)
        {
            $colarr[$col] = array();
            foreach ($array as $k => $row)
            {
                if ($order[1] == 'SORT_DATE')
                {
                    $colarr[$col]['_' . $k] = strtotime($row[$col]);
                    $cols[$col][1]          = 'SORT_NUMERIC';
                }
                else
                {
                    $colarr[$col]['_' . $k] = strtolower($row[$col]);
                }
            }
        }
        
        $eval = 'array_multisort(';
        foreach ($cols as $col => $order)
        {
            if (!is_array($order))
            {
                $order = array(
                    'SORT_ASC'
                );
            }
            
            $eval .= '$colarr[\'' . $col . '\'],' . implode(',', $order) . ',';
        }
        
        $eval = substr($eval, 0, -1) . ');';
        eval($eval);
        
             
        
        $ret = array();
        if ($colarr)
        {
            $colarr = current($colarr);
            foreach (array_keys($colarr) as $k)
            {
                $ids[] = substr($k, 1);
            }
            
            return $ids;
        }
    }
    
    public static function arrayDepth($array)
    {
        $max_indentation = 1;
        $array_str       = print_r($array, true);
        $lines           = explode("\n", $array_str);
        foreach ($lines as $line)
        {
            $indentation = (strlen($line) - strlen(ltrim($line))) / 4;
            if ($indentation > $max_indentation)
            {
                $max_indentation = $indentation;
            }
        }
        
        return ceil(($max_indentation - 1) / 2) + 1;
    }
    
 
    
    public static function clearEmptyItems($array, $newEnumeration = false,$preserveEmpty=false)
    {
        if (is_array($array))
        {
            $array2 = null;
            
            foreach ($array as $key => $value)
            {
                if (is_array($value))
                {
                    $value = XARRAY::clearEmptyItems($value, $newEnumeration);
                }
                
                $empty=$preserveEmpty?false:(empty($value));
                
                if (($empty) or ($value === null) or ($value === false) or ( is_string($value)&&trim($value) === '') && !$newEnumeration)
                {
                    unset($array[$key]);
                }
                elseif ($value)
                {
                    $array2[] = $value;
                }
            }
            
            if ($newEnumeration)
            {
                return $array2;
            }
            
            return $array;
        }
    }
    
    public static function convertArrayToDots($paramName, $paramSet, $sign = '.')
    {
        if (!empty($paramSet))
        {
            foreach ($paramSet as $objParam => $objVal)
            {
                $setVal[$paramName . $sign . $objParam] = $objVal;
            }
            
            return $setVal;
        }
    }
    
    public static function convertDotsToArray($array, $sign = '.')
    {
        if (!empty($array))
        {
            foreach ($array as $objParam => $objVal)
            {
                if (strpos($objParam, $sign) !== false)
                {
                    $ex                     = explode($sign, $objParam);
                    $setVal[$ex[0]][$ex[1]] = $objVal;
                }
                else
                {
                    $setVal[$objParam] = $objVal;
                }
            }
            
            return $setVal;
        }
    }
    


    public static function combine($arr, $arrToKey)
    {
        $i = 0;
        foreach ($arrToKey AS $value)
        {
            $arrCombined[$value] = $arr[$i];
            $i++;
        }
        
        return $arrCombined;
    }
    
    public static function array_recursive_search($needle, $haystack, $strict = false, $path = array())
    {
        if (!is_array($haystack))
        {
            return false;
        }
        
        foreach ($haystack as $key => $val)
        {
            if (is_array($val) && $subPath = array_searchRecursive($needle, $val, $strict, $path))
            {
                $path = array_merge($path, array(
                    $key
                ), $subPath);
                return $path;
            }
            elseif ((!$strict && $val == $needle) || ($strict && $val === $needle))
            {
                $path[] = $key;
                return $path;
            }
        }
        
        return false;
    }
    
    public static function multiarrayKeys($ar, $level = 0, $sl = 0)
    {
        if (($level) && ($level < $sl))
            return;
        $keys = array();
        foreach ($ar as $k => $v)
        {
            $keys[] = $k;
            if (is_array($ar[$k]))
                if ($ke = XARRAY::multiarray_keys($ar[$k], $level, $sl + 1))
                    $keys = array_merge($keys, $ke);
        }
        
        return array_unique($keys);
    }
    
    public static function multidim_value_key_collect($arr)
    {
        $ext = array();
        XARRAY::arrayWalkRecursive2($arr, 'multidim_value_key_collect');
        return multidim_value_key_collect(0, 0, 1);
    }
    
    public static function arrayWalkRecursive2(&$input, $funcname, $userdata = "")
    {
        if (!is_callable($funcname))
        {
            return false;
        }
        
        if (!is_array($input))
        {
            return false;
        }
        
        foreach ($input AS $key => $value)
        {
            if (is_array($input[$key]))
            {
                XARRAY::arrayWalkRecursive2($input[$key], $funcname, $userdata);
            }
            else
            {
                $saved_value = $value;
                $saved_key   = $key;
                if (!empty($userdata))
                {
                    $funcname($value, $key, $userdata);
                }
                else
                {
                    $funcname($value, $key);
                }
                
                if ($value != $saved_value || $saved_key != $key)
                {
                    $input[$key] = $value;
                }
            }
        }
        
        return true;
    }
    
    
    public static function sortArrayByArray($inputArray, $sortArray)
    {
        return array_replace(array_flip($sortArray), $inputArray);    
    }
    
    
    public static function asKeyVal($arr, $key)
    {
        $newArr = array();
        if (is_array($arr) AND !empty($arr))
        {
            foreach ($arr as $k => $val)
            {
                $newArr[$k] = $val[$key];
            }
            
            return $newArr;
        }
    }
    
    // $a[]=array('key'=>'val')  => a['key']='val'
    
    public static function arrToKeyArr($arr, $key, $field)
    {
        if (is_array($arr))
        {
            foreach ($arr as $val)
            {
                $new_arr[$val[$key]] = $val[$field];
            }
            
            return $new_arr;
        }
    }
    
    public static function array_intersect_key_recursive()
    {
        $arrs   = func_get_args();
        $result = array_shift($arrs);
        foreach ($arrs as $array)
        {
            foreach ($result as $key => $v)
            {
                if (!array_key_exists($key, $array))
                {
                    unset($result[$key]);
                }
                elseif (is_array($v))
                {
                    $result[$key] = XARRAY::array_intersect_key_recursive($array[$key], $v);
                }
            }
        }
        
        return $result;
    }
    
    public static function arrayMergePlus(&$arr1, &$arr2, $keys = false)
    {
        if (is_array($arr2))
        {
            while (list($k, $v) = each($arr2))
            {
                if ($keys)
                {
                    $arr1[$k] = $v;
                }
                else
                {
                    $arr1[] = $v;
                }
            }
            
            reset($arr1);
            reset($arr2);
        }
    }
    
    // a[id][param]=array(key=>val))
    // a[id][param_key]=param_val
    // =>     a[param_val]=valk;
    // ключевой параметр   //параметр преобразования //
    
    public static function arrToLev($arr, $param_key, $param, $key)
    {
        if ($arr)
        {
            foreach ($arr as $ar)
            {
                $newarr[$ar[$param_key]] = $ar[$param][$key];
            }
            
            return $newarr;
        }
    }
    
    
    
    public static function add_key_prefix($arr, $key)
    {
        if (is_array($arr))
        {
            while (list($k, $v) = each($arr))
            {
                $rarr[$key . $k] = $v;
            }
            
            return $rarr;
        }
    }
    
    
    public static function sortByField($array, $on, $order = 'asc')
    {
        $newArray      = array();
        $sortableArray = array();
        if (count($array) > 0)
        {
            foreach ($array as $k => $v)
            {
                if (is_array($v))
                {
                    foreach ($v as $k2 => $v2)
                    {
                        if ($k2 == $on)
                        {
                            $sortableArray[$k] = $v2;
                        }
                    }
                }
                else
                {
                    $sortableArray[$k] = $v;
                }
            }
            
            switch ($order)
            {
                case 'asc':
                    asort($sortableArray);
                    break;
                
                case 'dsc':
                    arsort($sortableArray);
                    break;
            }
            
            foreach ($sortableArray as $k => $v)
            {
                $newArray[$k] = $array[$k];
            }
        }
        
        return $newArray;
    }
}
?>
