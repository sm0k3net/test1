<?php

namespace X4\Classes;

class MultiSection
{

    var $screenedFields = array();
    var $Extended = array();
    var $Fields = array();
    var $Replacement = array();
    var $MFReplacement = array();
    var $MFFields = array();
    var $maindata = array();
    var $sectionNests = array();
    var $blockedparseSection = array();
    var $packOutput = false;
    var $fastReplace = array();
    var $MainFields = array();
    var $ifSplitPattern = '/(\{%(?:if\(.*?\))%\})|(\{%else%\})|(\{%endif%\})/';
    var $sectionSplitPattern = '/({%section:.+?%})|{%(endsection:.+?)%}|{%(each\(.+?\))%}|{%(endeach)%}/';
    var $varsExtractPattern = "/{%(F|MF|->):(.*?)?({(.*?)}%}|\((.*?)\)%}|%})/";
    var $coverExtractPattern = "/(\#|\@)(.*?)->(.*?)\((.*?)\)%}/";
    var $callFunc = array();
    var $sectionOverride = false;
    var $potentialKeys = array();
    var $noprsVals;
    var $timemark = 0;
    var $aifs;
    var $innerSectionNests;
    var $currentFile;
    var $ifstateBlock;
    var $currentRA;
    var $debug = false;

    public $templatesAlias = array();

    public function __construct()
    {

        XNameSpaceHolder::addMethodsToNS('TMS', array('_each'), $this);
        XNameSpaceHolder::addMethodsToNS('TMS', array('send', 'load', 'parse', 'cover'), $this);
        $this->timemark = \Common::generateHash(\Common::getmicrotime());

        $this->logger = new \Monolog\Logger('templates');
        $this->logger->pushHandler(new \Monolog\Handler\StreamHandler(PATH_ . 'logs/templates.log', \Monolog\Logger::INFO));

        if (\xConfig::get('GLOBAL', 'templateDebug')) {
            $this->debug = true;
        }

    }

    public function cover($params, $data)
    {
        $cover=$this->potentialKeys[$data['section']][$params['object']];
        if(!empty($cover)){
            return $cover->{$params['method']}($params);
        }
        return false;
    }



    public function parse($params, $data)
    {
        return $this->send($params, $data);
    }


    public function send($params, $data)
    {
        if ($data['TMS']) $TMS = $data['TMS']; else $TMS = $this;
        if ($TMS->isSectionDefined($params['section'])) {
            $TMS->addMassReplace($params['section'], $params['values']);
            return $TMS->parseSection($params['section']);

        } else {
            trigger_error('trying to send to non-existing section - | ' . $params['section'] . '|', E_USER_WARNING);
        }
    }

    /**
     * Detects section if defined
     * @param $section -section name
     * @return bool
     */
    public function isSectionDefined($section)
    {
        if (in_array($section, array_keys($this->maindata))) {
            return true;
        }
    }

    /**
     * Adds assoc array with variables
     * @param $section - section name
     * @param $arr key => value array with template variables
     */

    public function addMassReplace($section, $arr)
    {
        if (isset($arr) && is_array($arr)) {
            foreach ($arr as $key => $val) {
                $this->addReplace($section, $key, $val);
            }
        }
    }

    public function registerCover($cover)
    {
        $this->covers[$cover->coverName] = $cover;
    }

    /**
     * Add single variable to section
     * @param $section - section name
     * @param $addF - variable name
     * @param $addR - variable value
     */

    public function addReplace($section, $addF, $addR)
    {
        if (is_object($addR) && ($addR instanceof MultiSectionObject)) {

            $this->registerCover($addR);

        }

        $fkey = $this->findReplacement($section, $addF);

        if (is_array($addR)) {
            $this->setArrayDependency($section, $addF, $addR);
        }

        if ($fkey !== false) {
            $this->Fields[$section][$fkey] = $addR;

        } else {
            $this->potentialKeys[$section][$addF] = $addR;
        }
    }

    public function findReplacement($section, $repl)
    {
        if (isset($this->Replacement[$section])) {
            return array_search('{%F:' . $repl . '%}', $this->Replacement[$section]);

        }

        return false;
    }

    public function setArrayDependency($section, $addF, $addR)
    {
        static $z;

        if (isset($this->Replacement[$section])) {

            if (isset($z[$this->timemark][$section][$addF])) {
                $iterateIn = $z[$this->timemark][$section][$addF];

            } else {
                $iterateIn = $this->Replacement[$section];
            }

            foreach ($iterateIn as $rkey => $repl) {

                if (strpos($repl, '{%F:' . $addF . '>') !== false) {

                    if ($matched = preg_match($this->varsExtractPattern, $repl, $matches)) {

                        $tempAddr = $addR;

                        if (!empty($matches[2])) {
                            $s = explode('>', $matches[2]);

                            //   переменные ключи отключены из за скорости
                            // $s = $this->replaceSlice($section, $s);

                            $sCount = count($s);

                            for ($i = 1; $i < $sCount; $i++) {
                                $tempAddr = isset($tempAddr[$s[$i]]) ? $tempAddr[$s[$i]] : null;
                            }
                            $this->Fields[$section][$rkey] = $tempAddr;

                            $z[$this->timemark][$section][$addF][$rkey] = $repl;
                        }

                    }
                }
            }
        }

        if (isset($this->innerSectionNests[$section])) {
            foreach ($this->innerSectionNests[$section] as $innerSection) {
                $this->setArrayDependency($innerSection, $addF, $addR);
            }
        }


    }

    /**
     * @param $section section name to parse
     * @param null $data section vars assoc array
     * @return string rendered section output
     */

    public function render($section, $data = null)
    {
        if ($data) {

            $this->addMassReplace($section, $data);
        }

        return $this->parseSection($section);

    }

    /**
     * @param $section section name to parse
     * @param bool $glue force glueing to inner variable
     * @param null $forceReturn return data even if glueing enabled
     * @return string
     */

    public function parseSection($section, $glue = false, $forceReturn = null)
    {

        if (empty($this->maindata[$section])) {
            return '';
        }

        $sectionContentPrototype = $this->maindata[$section];
        $exitString = '';

        if (!empty($this->maindata[$section])) {
            if (strpos($this->maindata[$section], '{%if(') !== false) {
                $sectionContentPrototype = $this->implementLogic($section);
            } else {
                $this->callOuterFuncs($section, array(
                    '@',
                    '#'
                ));
            }
        }

        $this->potentialKeys[$section] = array();

        if (!empty($this->Replacement[$section]) && !empty($this->Fields[$section])) {
            $replacement = $this->Replacement[$section];
            $fields = $this->Fields[$section];

        } else {
            $fields = array();
            $replacement = array();
        }

        if (!empty($this->noprsVals[$section])) {
            foreach ($this->noprsVals[$section] as $nprsval) {
                if (($fkey = $this->findReplacement($section, $nprsval) !== false)) {
                    unset($fields[$fkey]);
                    unset($replacement[$fkey]);
                }
            }
        }

        $allReplacements = array_merge((array)$replacement, (array)$this->MFReplacement);
        $allFields = array_merge((array)$fields, (array)$this->MFFields);

        $sectionContent = str_replace($allReplacements, $allFields, $sectionContentPrototype);

        if ($sectionContent) {
            $exitString .= $sectionContent;
        }


        $this->callOuterFuncs($section, array('-'));

        if ((!empty($this->fastReplace[$section])) && (!$forceReturn)) {
            if ($glue) {
                $this->addMFReplace('{%->:' . $this->fastReplace[$section] . '%}', $exitString, true);
            } else {
                $this->addMFReplace('{%->:' . $this->fastReplace[$section] . '%}', $exitString);
            }
        } else {


            if ($this->debug && $this->currentFile && !strstr($section, '_each')) {

                $currentFile = $this->currentFile . '  |' . $section;

                if (!is_string($this->currentFile)) {
                    $currentFile = 'parsed from array  |' . $section;
                }

                return "\r\n<!--$currentFile-->\r\n" . $exitString . "\r\n<!--/$currentFile-->\r\n";

            } else {

                return $exitString;
            }
        }
    }

    public function implementLogic($section)
    {
        return $this->ifReverse($this->parseIf($section), $section);
    }

    public function ifReverse($aif, $section, $start = 0, $t = 0)
    {
        if (empty($aif)) return false;

        $result = '';
        if (!$start) {
            foreach ($aif[$start] as $iterm) {
                $t++;
                $it = current($iterm);
                $this->detectAndCallOuterFunc($it, $section);
                $result .= $it;
                if (isset($aif[$start + 1]) && isset($aif[$start + 1][$t]) && $aif[$start + 1][$t]) {
                    $result .= $this->ifReverse($aif, $section, $start + 1, $t);
                }
            }
            return $result;
        } else {
            $it = $aif[$start][$t];
            if (strlen(trim($it['logic'])) > 0) {


                if (!$logic = $this->checkLogic($section, $it['logic'])) {
                    $logic = 0;
                }


                $ra = $this->currentRA;

                $r = null;

                $condition = '$r=' . "($logic)?true:false;";
                try {

                    $er = @eval($condition);

                } catch (Error $e) {
                    echo 'logic:' . $it['logic'] . "\r\n";
                    echo 'condition:' . $condition . "r\n";
                    echo 'error:' . $e->getMessage();

                }

                $this->currentRA = null;

                if ($er === false && $error = error_get_last()) {

                    trigger_error('(IF) ERROR - section: |' . $section . '| logic: ' . $logic . ' ', E_USER_WARNING);
                }
                if ($r) {
                    $this->detectAndCallOuterFunc($it['if'], $section);
                    $result .= $it['if'];
                    $y = 0;
                    if (isset($aif[$start + 1]) && $aif[$start + 1]) {
                        foreach ($aif[$start + 1] as $ai) {
                            $y++;
                            if ($ai['source'] == 'if' && $ai['nestedfrom'] == $t) {
                                $result .= $this->ifReverse($aif, $section, $start + 1, $y);
                            }
                        }
                    }
                    if (isset($it['endif'])) {
                        $this->detectAndCallOuterFunc($it['endif'], $section);
                        $result .= $it['endif'];
                    }


                } elseif (isset($it['else'])) {
                    $this->detectAndCallOuterFunc($it['else'], $section);
                    $result .= $it['else'];
                    $y = 0;
                    if (isset($aif[$start + 1]) && $aif[$start + 1]) {
                        foreach ($aif[$start + 1] as $ai) {
                            $y++;
                            if ($ai['source'] == 'else' && $ai['nestedfrom'] == $t) {
                                $result .= $this->ifReverse($aif, $section, $start + 1, $y);
                            }
                        }
                    }
                    if (isset($it['endif'])) {
                        $this->detectAndCallOuterFunc($it['endif'], $section);
                        $result .= $it['endif'];
                    }

                } elseif (isset($it['endif']) && $it['endif']) {
                    $this->detectAndCallOuterFunc($it['endif'], $section);
                    $result .= $it['endif'];
                }
                return $result;
            }
        }
    }

    public function detectAndCallOuterFunc($text, $section)
    {
        static $z;
        $mark = md5($text);

        if (!isset($z[$mark])) {
            preg_match_all("/{%F:(\#|\@)(.*?)%}/", $text, $m);
            $z[$mark] = $m;
        } else {
            $m = $z[$mark];
        }

        if (isset($m[0][0]) && $m[0][0]) {
            foreach ($m[0] as $funcname) {

                if ($f = $this->callFunc[$section][md5($funcname)]) {
                    $this->outerCall($f, $section);
                }
            }
        }

    }

    public function outerCall($func, $section)
    {
        if (strstr($func['method'], ':')) {


            $x = \Common::getmicrotime();

            $sep = explode(':', $func['method']);
            $funcValues = $this->replaceSlice($section, $func['values']);
            $r = '';


            if ($sep[0] == 'php') {
                $r = $this->callphp($sep[1], $funcValues, $section, $func['return']);

            } else {

                $rKey = $this->findReplacement($section, $func['return']);
                $r = XNameSpaceHolder::call($sep[0], $sep[1], $funcValues, array('value' => $this->Fields[$section][$rKey], 'section' => $section, 'return' => $func['return'], 'TMS' => $this));
            }

            if ($func['return']) {
                if ($func['priority'] != '@') {
                    $this->addReplace($section, $func['return'], $r);
                } else {
                    $func['ffield'] = substr($func['ffield'], 4);
                    $func['ffield'] = substr($func['ffield'], 0, strlen($func['ffield']) - 2);
                    $this->addReplace($section, $func['ffield'], $r);
                }
            }


            if (\xConfig::get('GLOBAL', 'templateDebug')) {
                $y = \Common::getmicrotime() - $x;
                $this->logger->info($section . ':' . implode(' ', $func) . ' :' . $y);
            }
        }
    }

    public function replaceSlice($section, $slice)
    {

        if (!is_array($slice)) {
            return false;
        }

        $repArray = $this->getAllCurrentReplacements($section);

        if (!function_exists('X4\Classes\recursiveJsonReplace')) {
            function recursiveJsonReplace(&$val, &$key, $repArray)
            {
                if ((isset($repArray[$val])) && (is_array($repArray[$val]))) {

                    $val = $repArray[$val];

                } else {

                    if (strstr($val, '{F:')) {
                        foreach ($repArray as $k => $a) {
                            if (is_array($a)) {
                                unset($repArray[$k]);
                            }
                        }

                        $val = str_replace(array_keys($repArray), array_values($repArray), $val);
                    }
                }
            }
        }

        \XARRAY::arrayWalkRecursive2($slice, 'X4\Classes\recursiveJsonReplace', $repArray);

        return $slice;

    }

    public function getAllCurrentReplacements($section)
    {
        $repArray = array();
        $gsrRepArray = array();

        if (isset($this->potentialKeys[$section])) {
            foreach ($this->potentialKeys[$section] as $k => $v) {
                $repArray['{F:' . $k . '}'] = $v;
            }
        }

        if ($sr = $this->getSectionReplacements($section, '{F:', '}')) {
            $gsrRepArray = array_combine($sr, $this->Fields[$section]);
        }

        $repArray = array_merge($repArray, $gsrRepArray);

        return $repArray;
    }

    public function getSectionReplacements($section, $s = '', $e = '')
    {
        if (!empty($this->Replacement[$section])) {
            foreach ($this->Replacement[$section] as $repl) {
                $ext[] = $s . substr($repl, 4, strlen($repl) - 6) . $e;
            }
            return $ext;
        }
    }

    public function callphp($func, $args)
    {

        if (isset($args)) {
            $a = array();
            foreach ($args as $arg) {
                if (is_array($arg)) {
                    $a[] = var_export($arg, true);
                } else {
                    $a[] = $arg;
                }
            }
        }
        $r = false;
        if (count($a) > 0) {
            $t = '$r=' . $func . '(' . implode($a, ',') . ');';
        } else {
            $t = '$r=' . $func . '(' . $a . ');';
        }
        @eval($t);
        return $r;
    }

    public function checkLogic($section, $logic)
    {

        $repArray = $this->getAllCurrentReplacements($section);

        preg_match_all('/{F:(.*?)}/', trim($logic), $varsInLogic);

        $repArrayExport = array();
        $repArrayExportQ = array();
        $repArrayExportZ = array();

        if (isset($varsInLogic[0])) {
            foreach ($varsInLogic[0] as $val) {


                if (!isset($repArray[$val])) {
                    $this->currentRA[$val] = NULL;

                } else {
                    $this->currentRA[$val] = $repArray[$val];
                }

                //hack to prevent quotes MUST BE REMOVED!
                array_push($repArrayExportQ, "'" . '$ra["' . $val . '"]' . "'");
                array_push($repArrayExportZ, '$ra["' . $val . '"]');
                $repArrayExport[$val] = '$ra["' . $val . '"]';

            }

            $logic = str_replace(array_keys($repArrayExport), $repArrayExport, $logic);
            return $logic = str_replace($repArrayExportQ, $repArrayExportZ, $logic);


        }

    }

    public function parseIf($section)
    {
        if ($this->aifs[$section])
            return $this->aifs[$section];
        if ($tcode = preg_split($this->ifSplitPattern, $this->maindata[$section], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY)) {
            $lev = 0;
            $k = 0;
            $aif = array();
            foreach ($tcode as $citem) {
                if (preg_match('/\{%(?:if\((.*?)\))%\}/', $citem, $m)) {
                    $lev++;
                    if (isset($aif[$lev])) {
                        $aifCount = count($aif[$lev]);
                    } else {
                        $aifCount = 0;
                    }

                    $k = $aifCount + 1;
                    $aif[$lev][$k]['logic'] = $m[1];
                    $aif[$lev][$k]['type'] = $aif[$lev][$k]['lt'] = 'if';
                    $aif[$lev][$k]['nestedfrom'] = count($aif[$lev - 1]);

                    if (isset($aif[$lev - 1]) && isset($aif[$lev - 1][count($aif[$lev - 1])])) {
                        $aif[$lev][$k]['source'] = $aif[$lev - 1][count($aif[$lev - 1])]['lt'];
                    }
                } elseif (trim($citem) == '{%else%}') {
                    $aif[$lev][$k]['lt'] = $aif[$lev][$k]['type'] = 'else';
                } elseif (trim($citem) == '{%endif%}') {
                    if ($lev > 0)
                        $aif[$lev][$k]['type'] = 'endif';
                } else {
                    if (isset($aif)) {

                        if ($aif[$lev][$k]['type'] == 'endif') {
                            if ($lev == 1) {
                                $k = count($aif[$lev - 1]);
                                $aif[0][$k][$aif[$lev][$k]['type']] = $citem;
                            } else {
                                $aif[$lev][$k][$aif[$lev][$k]['type']] = $citem;
                            }
                            $lev--;
                            $k = count($aif[$lev]);
                        } else {
                            $aif[$lev][$k][$aif[$lev][$k]['type']] = $citem;
                        }
                    }
                }
            }
            return $this->aifs[$section] = $aif;
        }
    }

    public function callOuterFuncs($section, $priority = array('#'))
    {
        if (isset($this->callFunc[$section]) && is_array($this->callFunc[$section])) {
            foreach ($this->callFunc[$section] as $func) {
                if (in_array($func['priority'], $priority)) {


                    $this->outerCall($func, $section);


                }
            }
        }
    }

    public function addMFReplace($addMF, $addMR, $glue = false)
    {
        if ($strop = strpos($addMF, '%->:')) {
            $fkey = array_search($addMF, $this->MFReplacement);
        } else {
            $fkey = array_search('{%MF:' . $addMF . '%}', $this->MFReplacement);
        }
        if ($fkey !== false) {
            if ($glue) {
                $this->MFFields[$fkey] .= $addMR;

            } else {
                $this->MFFields[$fkey] = $addMR;

            }
        }
    }


    public function clearSectionFelds($section, $el = '')
    {
        if ($this->Fields[$section]) {
            $this->Fields[$section] = array_fill(0, count($this->Fields[$section]), $el);
        }
    }

    public function delSection($section, $is_prefix = null)
    {
        if (is_array($section)) {
            foreach ($section as $sec_item) {
                if ($this->maindata[$sec_item]) {
                    unset($this->maindata[$sec_item]);
                    unset($this->Replacement[$sec_item]);
                    unset($this->Fields[$sec_item]);
                }
            }
        } elseif ($is_prefix) {
            $existing_section = array_keys($this->maindata);
            foreach ($existing_section as $existing_item) {
                if (strpos($existing_item, $section) === 0) {
                    unset($this->maindata[$existing_item]);
                    unset($this->Replacement[$existing_item]);
                    unset($this->Fields[$existing_item]);
                    unset($this->fastReplace[$existing_item]);
                }
            }
            foreach ($this->MFReplacement as $num => $value) {
                if (strpos($this->MFReplacement[$num], '{%->:' . $section) === 0) {
                    unset($this->MFReplacement[$num]);
                    unset($this->MFFields[$num]);
                }
            }
        } else {
            if ($this->maindata[$section]) {
                unset($this->maindata[$section]);
                unset($this->Replacement[$section]);
                unset($this->Fields[$section]);
                unset($this->Extended[$section]);
            }
        }
    }

    /**
     * Returns all template data
     * @return array
     */
    public function returnData()
    {
        return $this->maindata;
    }

    public function getMFSectionReplacements()
    {

        if (isset($this->MFReplacement)) {
            $ext = [];
            foreach ($this->MFReplacement as $repl) {
                preg_match("/{%->:(.*?)%}/", $repl, $match);
                $ext[] = $match[1];
            }
            return $ext;
        }
    }

    public function getFastReplace()
    {
        $fr = array_keys($this->fastReplace);

        if (!empty($fr)) {
            foreach ($fr as $v) {
                $f[] = str_replace('@', '', $v);
            }
            return $f;
        }

        return null;
    }

    public function killField($section, $Repl)
    {
        $fkey = $this->findReplacement($section, $Repl);
        if ($fkey !== false) {
            $this->Fields[$section][$fkey] = "";
        }
    }

    public function killMFields($FastSection)
    {
        $fkey = array_search('{%->:' . $FastSection . '%}', $this->MFReplacement);
        if ($fkey !== false) {
            $this->MFFields[$fkey] = '';
        }
    }

    public function addMFMassReplace($arr)
    {
        if ($arr) {
            foreach ($arr as $key => $val) {
                $this->addMFReplace($key, $val);
            }
        }
    }

    public function setSectionOverride($state)
    {
        $this->sectionOverride = $state;
    }

    public function generateSection($text, $sectionName)
    {
        $this->addFileSection('{%section:' . $sectionName . "%}\r\n" . $text . "\r\n{%endsection:" . $sectionName . '%}', true);
    }

    /**
     * Adds file with template sections
     * @param $filename - filename to parse
     * @param bool $astext - parse as text
     * @return mixed
     * @throws \Exception
     */

    public function addFileSection($filename, $astext = false)
    {
        if (is_array($filename)) {
            $this->processIncluded($filename);
        } elseif (file_exists($filename)) {
            $this->currentFile = $filename;
            $this->processIncluded(file($filename));

            if (isset($this->templatesAlias[$filename])) return $this->templatesAlias[$filename];

        } elseif ($astext) {
            $this->processIncluded(explode("\n", $filename));

        } else {

            throw new \Exception('Template not found -> ' . $filename);
        }
    }

    public function processIncluded($lines)
    {
        if ($lines[0][0] == '@') $this->templatesAlias[$this->currentFile] = substr($lines[0], 1);
        $eachStart = false;
        $sectionName = '';
        $l = implode($lines);
        $sectonStack = array();
        $sectionStart = false;
        if ($tcode = preg_split($this->sectionSplitPattern, $l, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY)) {
            foreach($tcode as $line=>&$code){
                if (strpos($code, '{%section:') !== false) {
                    $sectionStart = preg_match("!{%section:(.+?)(%}|->(.*?)%})!si", $code, $sectionInfo);
                    if ($this->createSection($sectionInfo))
                        $sectionName = $sectionInfo[1];
                } elseif (strpos($code, 'endsection:') === 0) {
                    $sectionStart = false;
                    $this->processSectionVars($sectionName);
                    $this->callOuterFuncs($sectionName, array('+'));
                    $sectionName = array_pop($sectonStack);
                } elseif ((strpos($code, 'each(') === 0) && ($sectionStart)) {
                    $tempSection = $sectionName;
                    array_push($sectonStack, $sectionName);
                    $sectionName = '_each' . $line . md5($code . $tempSection);
                    $this->addToSection($tempSection, '{%F:@' . $sectionName . '(TMS:_' . $code . ')%}');
                    $this->innerSectionNests[$tempSection][] = $sectionName;
                    $this->createSection(array('', $sectionName));
                } elseif (($code == 'endeach') && ($sectionStart)) {
                    $this->processSectionVars($sectionName);
                    $sectionName = array_pop($sectonStack);
                } elseif (($sectionStart or $eachStart) && $sectionName) {
                    $this->addToSection($sectionName, $code);
                }
            }
        }
    }

    public function createSection($sectionInfo)
    {
        if ((!$this->isSectionDefined($sectionInfo[1])) or ($this->sectionOverride)) {
            $sectionName = $sectionInfo[1];

            if (isset($sectionInfo[2]) && ($sectionInfo[2] == '->%}')) {
                $this->fastReplace[$sectionName] = $sectionName;

            } elseif (isset($sectionInfo[3])) {
                $this->fastReplace[$sectionName] = $sectionInfo[3];

            }

            $this->maindata[$sectionName] = '';
            return true;

        } else {
            unset($this->aifs[$sectionInfo[1]]);
        }
    }

    private function detectCoverMarker($match, $k)
    {
        preg_match_all($this->coverExtractPattern, $match[2][$k] . $match[3][$k], $m);
        if (!empty($m[3])) {


            if($m[1][0]=='@'){
                $name='@'.md5($m[0][0]);
            }else{
                $name=$m[1][0] .md5($m[0][0]);
            }

            if (empty($m[4][0])) {
                $params='"'.$m[4][0].'"';
            }else{
                $params=$m[4][0];
            }
            $callPart = 'TMS:cover({"object":"' . $m[2][0] . '","method":"' . $m[3][0] . '","params":' . $params . '})';
            $match[0][$k] = '{%F:' . $name . '(' . $callPart . ')%}';
            $match[3][$k] = '(' . $callPart . ')%}';
            $match[2][$k] = $name;
            $match[5][$k] = $callPart;



            return $match;
        }
        return false;
    }

    public function processSectionVars($sectionName)
    {

        if (isset($this->maindata[$sectionName])) {

            if (preg_match_all('/\{%?if\((.*?)\)%\}/', $this->maindata[$sectionName], $ifMatched)) {

                foreach ($ifMatched[1] as $ifm) {
                    $this->parseFuncValues($ifm, $sectionName, true);
                }
            }

            if ($matched = preg_match_all($this->varsExtractPattern, $this->maindata[$sectionName], $match)) {
                $k = 0;
                foreach ($match[0] as $field) {
                    if ($match[1][$k] == 'MF') {
                        $this->MainFields[] = $match[2][$k];
                        $this->MFReplacement[] = '{%MF:' . $match[2][$k] . '%}';
                        $this->MFFields[] = '';

                        $k++;
                    } elseif ($match[1][$k] == '->') {
                        $this->sectionNests[$sectionName][] = $match[2][$k];
                        $this->MFReplacement[] = '{%->:' . $match[2][$k] . '%}';
                        $this->MFFields[] = '';

                        $k++;
                    } else {

                        $field = trim($field);
                        $fkey = false;
                        if (isset($this->Replacement[$sectionName])) {
                            $fkey = array_search($field, $this->Replacement[$sectionName]);
                        }


                        if ($fkey === false) {

                            $_field = '{%F:' . $match[2][$k] . '%}';

                            if ($detected = $this->detectCoverMarker($match, $k)) {
                                $match = $detected;
                                $this->maindata[$sectionName]=str_replace($field,$match[0][$k],$this->maindata[$sectionName]);
                                $_field = '{%F:' . $match[2][$k] . '%}';
                                $field=$match[0][$k];

                            }


                            if ($match[5][$k]) {

                                preg_match('/(.*?)\((.*?)\)/', $match[5][$k], $m);

                                if (substr($match[2][$k], -1) == '=') {
                                    $match[2][$k] = '#' . substr($match[2][$k], 0, (strlen($match[2][$k]) - 1));
                                }

                                if (($match[2][$k][0] == '#') or ($match[2][$k][0] == '+') or ($match[2][$k][0] == '-') or ($match[2][$k][0] == '@')) {
                                    if ($retval = substr($match[2][$k], 1)) {

                                        if (!isset($this->ifstateBlock['{%F:' . $retval . '%}'])) {
                                            if (!isset($this->Replacement[$sectionName])) $this->Replacement[$sectionName] = array();
                                            $fieldKey = array_search('{%F:' . $retval . '%}', $this->Replacement[$sectionName]);

                                            if ($fieldKey === false) {
                                                $this->Fields[$sectionName][] = "";
                                                $this->Replacement[$sectionName][] = '{%F:' . $retval . '%}';
                                            }
                                        }

                                    }

                                    $jsonStop = false;

                                    if ($m[1] == 'TMS:_each') {
                                        $jsonStop = true;
                                    }

                                    $this->callFunc[$sectionName][md5($field)] = array(
                                        'method' => $m[1],
                                        'values' => $this->parseFuncValues($m[2], $sectionName, $jsonStop),
                                        'index' => count($this->Fields[$sectionName]),
                                        'priority' => $match[2][$k][0],
                                        'return' => $retval,
                                        'ffield' => $match[0][$k]
                                    );

                                } else {
                                    $fkey = false;
                                    if ($this->Replacement[$sectionName]) {
                                        $fkey = array_search('{%F:' . $match[2][$k] . '%}', $this->Replacement[$sectionName]);
                                    }
                                    if ($fkey === false) {
                                        $this->Fields[$sectionName][] = "";
                                        $this->Replacement[$sectionName][] = '{%F:' . $match[2][$k] . '%}';
                                    }

                                }
                                $_field = $field;
                            }

                            $this->Fields[$sectionName][] = "";
                            $this->Replacement[$sectionName][] = $_field;

                        }
                        $k++;
                    }
                }

                $match = null;
            }
        }
    }

    public function parseFuncValues($text, $sectionName, $ifsState = false)
    {

        if ($text) {
            if (preg_match_all('/{F:(.*?)}/', $text, $m)) {
                foreach ($m[1] as $f) {
                    $fkey = false;
                    if (isset($this->Replacement[$sectionName]) && $this->Replacement[$sectionName]) {
                        $fkey = array_search('{%F:' . $f . '%}', $this->Replacement[$sectionName]);
                    }
                    if ($fkey === false) {
                        $this->Fields[$sectionName][] = "";
                        $this->Replacement[$sectionName][] = '{%F:' . $f . '%}';

                        if ($ifsState) {
                            $this->ifstateBlock['{%F:' . $f . '%}'] = 1;
                        }
                    }
                }
            }

            if (!$ifsState) {
                $quotesNum = substr_count($text, '"');

                if ($quotesNum % 2 != 0) {

                    trigger_error('JSON parse error quotes number(' . $quotesNum . ') non-closed quotes | ' . $text . ' | see section: ' . $sectionName, E_USER_ERROR);

                }


                $quotesNum = substr_count($text, '"');

                if ($quotesNum % 2 != 0) {

                    trigger_error('JSON parse error quotes number(' . $quotesNum . ') non-closed quotes | ' . $text . ' | see section: ' . $sectionName, E_USER_ERROR);

                }

                if (!$result = json_decode($text, true)) {
                    trigger_error('JSON parse error - | ' . $text . ' | see section: ' . $sectionName, E_USER_WARNING);

                } else {

                    return $result;
                }

            } else {

                return explode(',', $text);
            }

        }
    }

    public function addToSection($sectionName, $text)
    {
        if ($this->isSectionDefined($sectionName)) {
            $this->maindata[$sectionName] .= $text;
        }
    }

    public function _each($params, $section)
    {
        if (isset($section['TMS'])) {
            $TMS = $section['TMS'];
        } else {
            $TMS = $this;
        }

        if (!is_array($params[0]) && !empty($params[0])) {

            $intval = intval($params[0]);
            $params[0] = range(0, $intval);

        }

        if (is_array($params[0])) {

            if ($TMS->Replacement[$section['section']] && $TMS->Fields[$section['section']]) {
                $scopeGlobal = array_combine($TMS->getSectionReplacements($section['section']), $TMS->Fields[$section['section']]);
                $TMS->addMassReplace($section['return'], $scopeGlobal);
            }

            $TMS->potentialKeys[$section['return']] = isset($TMS->potentialKeys[$section['section']]) ? $TMS->potentialKeys[$section['section']] : null;

            $i = 0;

            $arrayLength = count($params[0]);
            $eachText = '';
            foreach ($params[0] as $key => $value) {

                if ($params[2]) {
                    $scopeLocal = array(
                        $params[1] => $key,
                        $params[2] => $value
                    );

                } else {
                    $scopeLocal = array(
                        $params[1] => $value
                    );
                }

                if (0 == $i) {
                    $scopeLocal['each.first'] = true;
                } else {
                    $scopeLocal['each.first'] = false;
                }

                $scopeLocal['each.iterator'] = $i++;
                $scopeLocal['each.odd'] = $i % 2;

                if ($arrayLength == $i) {
                    $scopeLocal['each.last'] = true;
                }

                $TMS->addMassReplace($section['return'], $scopeLocal);
                $eachText .= $TMS->parseSection($section['return']);
            }
            //back scope
            if ($TMS->Fields[$section['return']]) {
                $backScope = array_combine($TMS->getSectionReplacements($section['return']), $TMS->Fields[$section['return']]);
            }
            $this->addMassReplace($section['section'], $backScope);

            return $eachText;
        }
        return false;
    }

    public function load($params)
    {

        if (!isset($params['prefix']) or !$defaultPath = \xConfig::get('PATH', $params['prefix'])) {
            $defaultPath = \xConfig::get('PATH', 'TEMPLATES');
        }

        if ($adm = XRegistry::get('ADM')) {

            $cnt = $adm->tplLangConvert(null, $defaultPath . $params['path'], $params['module']);

        } else {
            $cnt = file_get_contents($defaultPath . $params['path']);
        }

        $this->addFileSection($cnt, true);
    }

    public function addMassFileSection($fileSections)
    {
        if (is_array($fileSections)) {
            foreach ($fileSections as $fileSection) {
                $this->addFileSection($fileSection);
            }
        }
    }

    public function parseRecursive($sectionName, $glue = 0)
    {
        if (isset($this->maindata[$sectionName])) {
            if (isset($this->sectionNests[$sectionName]) && $sect_count = count($this->sectionNests[$sectionName])) {
                for ($i = 0; $i < $sect_count; $i++) {
                    $this->parseRecursive($this->sectionNests[$sectionName][$i], true);
                }
            }

            if ($glue) {
                $this->parseSection($sectionName);
            } else {
                return $this->parseSection($sectionName);
            }
        }
    }

    public function noparse($section)
    {
        return $this->maindata[$section];

    }

    public function noparseVals($section, $vals)
    {
        $this->noprsVals[$section] = $vals;
    }
}
