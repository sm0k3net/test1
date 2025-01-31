<?php

class XHTML
{
    static function xssClean($data)
    {

        // Fix &entity\n;

        $data = str_replace(array(
            '&amp;',
            '&lt;',
            '&gt;'
        ), array(
            '&amp;amp;',
            '&amp;lt;',
            '&amp;gt;'
        ), $data);
        $data = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $data);
        $data = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $data);
        $data = html_entity_decode($data, ENT_COMPAT, 'UTF-8');

        // Remove any attribute starting with "on" or xmlns

        $data = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data);

        // Remove javascript: and vbscript: protocols

        $data = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $data);
        $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $data);
        $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u', '$1=$2nomozbinding...', $data);

        // Only works in IE: <span style="width: expression(alert('Ping!'));"></span>

        $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
        $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?behaviour[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
        $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#iu', '$1>', $data);

        // Remove namespaced elements (we do not need them)

        $data = preg_replace('#</*\w+:\w[^>]*+>#i', '', $data);
        do {

            // Remove really unwanted tags

            $old_data = $data;
            $data = preg_replace('#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i', '', $data);
        } while ($old_data !== $data);

        // we are done...

        return $data;
    }


    private static function optionAgregator($optionsArr, $selected = '', $group = false, $groupName = false)
    {
        $newarr = array();
        foreach ($optionsArr as $key => $val) {
            $element = array(
                'value' => $key,
                'text' => $val
            );
            if (is_array($selected)) {
                $fkey = array_key_exists($key, $selected);
                if ($fkey !== false) {
                    $element['selected'] = true;
                }
            } elseif ($selected == $key) {
                $element['selected'] = true;
            }

            if ($group) {
                $element['group'] = $groupName[$group] ? $groupName[$group] . ' ' : '' . $group;
            }

            $newarr[] = $element;
        }

        return $newarr;
    }


    public static function arrayToTable($array, $null = '&nbsp;')
    {

        if (empty($array) || !is_array($array)) {
            return false;
        }

        if (!isset($array[0]) || !is_array($array[0])) {
            $array = array($array);
        }


        $table = "<table>\n";

        // The body
        foreach ($array as $row) {
            $table .= "\t<tr>";
            foreach ($row as $cell) {
                $table .= '<td>';
                $table .= (strlen($cell) > 0) ?
                    htmlspecialchars((string)$cell) : $null;
                $table .= '</td>';
            }

            $table .= "</tr>\n";
        }

        $table .= '</table>';

        return $table;
    }


    // генерация массива для json передачи options

    public static function arrayToXoadSelectOptions($optionsArr, $selected = '', $addEmpty = false, $groupsName = false)
    {
        if (is_array($optionsArr) AND !empty($optionsArr)) {
            if ($addEmpty) {
                $newArray[] = array(
                    'value' => '',
                    'text' => ''
                );
            } else {
                $newArray = array();
            }

            if (XARRAY::arrayDepth($optionsArr) > 1) {
                foreach ($optionsArr as $optName => $optGroup) {
                    if ($arrOpts = XHTML::optionAgregator($optGroup, $selected, $optName, $groupsName)) {
                        $newArray = array_merge($newArray, $arrOpts);
                    }
                }
            } else {
                if ($arrOpts = XHTML::optionAgregator($optionsArr, $selected)) {
                    $newArray = array_merge($newArray, $arrOpts);
                }
            }

            return $newArray;
        }

        return false;
    }
}
