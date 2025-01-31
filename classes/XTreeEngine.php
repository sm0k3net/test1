<?php

namespace X4\Classes;

class xteBooster
{
    public $activeCache = array();
    public $driverInitiated = false;
    private $module;

    public function __construct($module)
    {
        \Common::loadDriver('XCache', 'XCacheRedisDriver');
        $this->driverInitiated = \XCacheRedisDriver::initDriver(true);
        $this->module = $module;
    }

    public static function clear()
    {
        \XCacheRedisDriver::clear();
    }

    public function getMultiById($data)
    {

        return \XCacheRedisDriver::getMulti($data, $this->module);
    }

    public function getById($id, $treeName)
    {
        if (!$this->activeCache[$treeName][$id]) {
            if ($dta = \XCacheRedisDriver::read($this->module, $id)) {
                if (!$this->activeCache[$treeName]) {
                    $this->activeCache[$treeName] = array();
                }
                $this->activeCache[$treeName] = $this->activeCache[$treeName] + $dta;
            }
        }

        return $this->activeCache[$treeName][$id];
    }

    public function saveRange($items)
    {
        if (isset($items)) {
            foreach ($items as $item) {
                $itemsKeys[$item['id']] = $item;
            }

            \XCacheRedisDriver::setMulti($itemsKeys, $this->module);
        }
    }

}

/**
 *  дочерний класс объектов к xte
 *  используется для получения результатов в виде дерева
 *  дерево храниться в виде матрицы смежности с исключенными нулевыми пересечениями
 */
class xteTree
{
    public $tree;
    public $nodes;
    private $startNode;

    public function __construct($nodes, $startNode)
    {
        $this->startNode = $startNode;
        if (is_array($nodes)) {
            foreach ($nodes as $key => $val) {
                $this->nodes[$val['id']] = $val;
                $this->tree[$val['ancestor']][$val['id']] = $val;
            }
            $this->sortTreeItems();
        }
    }

    /**
     * удалить все данные о ноде
     * @param mixed $id - id ноды
     */
    public function sortTreeItems()
    {
        foreach ($this->tree as &$val) {
            $val = \XARRAY::sortByField($val, 'rate', 'asc');
        }
    }

    public function remove($id)
    {
        unset($this->nodes[$id]);
        unset($this->tree[$id]);
        $nLength = count($this->tree);
        $keysNodes = array_keys($this->tree);
        for ($i = 0; $i < $nLength; $i++) {
            $subLength = count($this->tree[$keysNodes[$i]]);
            $subKeysNodes = array_keys($this->tree[$keysNodes[$i]]);
            for ($j = 0; $j < $subLength; $j++) {
                if ($subKeysNodes[$j] == $id) {
                    unset($this->tree[$keysNodes[$i]][$subKeysNodes[$j]]);
                }
            }
        }
    }

    /**
     * есть ли дочерние элементы
     *
     * @param mixed $id
     */

    public function hasChilds($id)
    {
        if (isset($this->tree[$id]))
            return true;
    }


    public function getNode($id)
    {
        return $this->nodes[$id];
    }

    public function __get($id)
    {
        return $this->nodes[$id];
    }

    /**
     *  подсчитать количество элементов в ветке
     *
     * @param mixed $id
     * @return int
     */
    public function countBranch($id)
    {
        return count($this->tree[$id]);
    }

    /**
     * получить массив нод по значению предка
     * @param mixed $ancestor - id предка
     */
    public function fetchArray($ancestor)
    {
        if (isset($this->tree[$ancestor]))
            return $this->tree[$ancestor];
    }

    /**
     * Рекурсивная  прохождение по дереву значений  с использованием callback функции
     *
     * @param int $startNode - стартовая нода
     * @param object $context -контекст callback функции
     * @param string $function - имя функции
     * @param mixed $extdata - необязательный параметр даполнительных данных
     */
    public function recursiveStep($startNode, $context, $function, $extdata = null)
    {
        if (isset($this->tree[$startNode])) {


            foreach ($this->tree[$startNode] as &$node) {
                if (isset($this->tree[$node['id']])) {
                    call_user_func_array(array(
                        $context,
                        $function
                    ), array(
                        $node,
                        $startNode,
                        $this,
                        $extdata
                    ));
                    $this->recursiveStep($node['id'], $context, $function);
                } else {
                    call_user_func_array(array(
                        $context,
                        $function
                    ), array(
                        $node,
                        $startNode,
                        $this,
                        $extdata
                    ));
                }
            }
        }
    }

    /**
     * функция для фетчинга нод дерева последовательно[заменить на Iterator]
     *
     * @param mixed $ancestor
     * @return mixed
     */
    public function fetch($ancestor = null)
    {
        static $reset;
        if (!$ancestor) {
            $ancestor = $this->startNode;
        }

        if (empty($this->tree[$ancestor])) {
            return;
        }

        if (empty($reset[$ancestor])) {
            $obj = new \ArrayObject($this->tree[$ancestor]);
            $reset[$ancestor] = $obj->getIterator();
        }

        while ($reset[$ancestor]->valid()) {
            $v = [
                $reset[$ancestor]->key(),
                $reset[$ancestor]->current()
            ];

            $reset[$ancestor]->next();
            return $v;

        }

        $reset[$ancestor] = null;
        return;

    }
}

class XTreeEngine implements \repoProvider
{
    public static $UNIQ_ANCESTOR = 1;
    public static $UNIQ_TREE = 2;
    public static $maxCounter = false;
    public $treeStructName;
    public $treeParamName;
    public $levelOffset = 5;
    public $useInLenghToIntersect = 100;
    public $lastNonUniqId;
    public $ExceptionHandlers = array();
    public $PDO;
    public $recordsFormatCache;
    public $dataPump = false;
    public $autoPumpNumber = 0;
    public $nodesAllCount = 0;
    public $treeBoost = null;
    public $treeBoosted = false;
    public $preventBasicCheck = false;
    public $cacheQueryLongerThan = 1;
    public $treeName;
    public $nodeIntersectAll;
    public $queryLog;
    public $innerTree;
    private $query;
    private $levels = 12;
    private $uniqType;
    private $filter;
    private $nodeCache = array();
    private $nodeCacheStruct = array();
    private $lockObjType;
    private $formatTypes = array('normal', 'keyval');
    private $nativeStructFieldsList = array('id', 'obj_type', 'rate', 'basic', 'disabled');
    private $dataPumpIncrement;
    private $cacheDir = 'tree';
    private $enableCache = false;
    private $cacheTimeout = 86400;
    private $netModel = false;
    private $pumpDataNodeToCheck;
    private $pumpBox;
    private $seed;

    public function __construct($treeName, $PDO = null, $uniqType = 1)
    {
        if (!$PDO)
            $PDO = XRegistry::get('XPDO');
        $this->PDO = $PDO;
        $this->treeStructName = strtolower("_tree_" . $treeName . "_struct");
        $this->treeParamName = strtolower("_tree_" . $treeName . "_param");
        $this->treeName = $treeName;
        $this->cacheDir .= '/' . $this->treeName;
        $this->uniqType = $uniqType;
        $this->seed = rand(0, 1000);
        $this->cacheState(false);
    }

    public function cacheState($enable, $cacheTimeOut = null)
    {
        $this->enableCache = $enable;

        if ($cacheTimeOut) {
            $this->setCacheTimeout($cacheTimeOut);
        }
    }

    public function setCacheTimeout($timeOut = 3600)
    {
        $this->cacheTimeout = $timeOut;
    }


    public function setCheckBasicPrevention($state = true)
    {
        $this->preventBasicCheck = $state;
    }

    public function setLevels($levels)
    {
        $this->levels = $levels;
    }


    public function setDataPump($state, $autoPumpNumber = 0)
    {
        $this->dataPump = $state;
        $this->autoPumpNumber = $autoPumpNumber;
        if ($pdoResult = $this->PDO->query("SHOW TABLE STATUS LIKE '{$this->treeStructName}'")) {
            $row = $pdoResult->fetch();
        }
        $this->dataPumpIncrement = $row['Auto_increment'];
    }

    public function setNetModel($state)
    {
        $this->netModel = $state;
    }

    public function setUniqType($uniqType = 1)
    {
        $this->uniqType = $uniqType;
    }

    /**
     * Implements export to repository
     *
     * @param mixed $objId
     * @param mixed $childMode true - get childs
     */
    public function startBooster()
    {
        $this->treeBoost = new xteBooster($this->treeStructName);
        $this->treeBoosted = null;

    }

    public function setTreeBoosted()
    {
        $this->treeBoosted = true;
    }

    public function boostById($id, $range = 400)
    {
        if ($this->treeBoost) {
            $start = 0;
            $res = true;
            while ($res) {
                $res = $this->selectParams('*')->selectStruct('*')->childs($id)->sortby('@id', 'asc')->limit($start, $range)->run();
                $this->treeBoost->saveRange($res, $this->treeStructName);
                $start += $range;
            }
        }
    }

    public function preventSingleResult()
    {
        $this->query['preventSingleResult'] = true;
        return $this;
    }

    public function run()
    {
        $startTime = \Common::getmicrotime();

        if ($this->query['noResults']) {
            $this->queryLog[] = $this->query;
            unset($this->query);
            return false;
        }

        $selectQs = $this->getSelectQueryString();
        $whereQs = $this->getWhereQueryString();
        $this->query['pathCache'] = array();

        if (!isset($this->query['selectStruct']) && isset($this->query['selectParams'])) {
            $this->query['selectStruct'] = array(
                'id'
            );
            $selectQs['selectStructString'] = 'id';
        }
        if (isset($this->query['selectCount']) && $this->query['selectCount']) {
            $this->query['selectStruct'] = array(
                'id'
            );
            $selectQs['selectStructString'] = 'id';
        }
        if (!isset($this->query['delete'])) {
            $mark = \Common::createMark($this->query);
            $markQuery = $this->query;
            if ($this->enableCache && $result = XCache::serializedRead($this->cacheDir, $mark, $this->cacheTimeout)) {
                if (!($result instanceOf xteTree)) {
                    $this->nodesAllCount = $result['nodesAllCount'];
                    $this->nodeIntersectAll = $result['nodeIntersectAll'];
                    $this->nodesAllCount = $result['nodesAllCount'];
                    unset($result['nodesAllCount'], $result['nodeIntersectAll']);
                }
                $this->queryLog[] = $this->query;
                unset($this->query);
                return $result;
            }
        }
        $this->nodesAllCount = 0;
        $isParamsFirst = $whereQs['whereStructString'] == ' disabled=0 ' && isset($whereQs['whereParamsString']);
        if (isset($whereQs['whereStructString']) && !$isParamsFirst) {
            $queryStr = $this->buildStructQuery($selectQs, $whereQs);
            $nStructResults = $this->getStructResults($queryStr);
            if (isset($nStructResults)) {
                $nParamsResults = $this->getParamsResults($whereQs);
            }
        } else {
            $nParamsResults = $this->getParamsResults($whereQs);
            if (count($nParamsResults) > 0) {
                $whereQs['inIdArr'] = $nParamsResults;
                $queryStr = $this->buildStructQuery($selectQs, $whereQs);
                $nStructResults = $this->getStructResults($queryStr);
            }
        }
        // no intersection no result
        if (isset($nStructResults) && $nStructResults && !empty($nParamsResults) && $nodesIntersect = array_intersect($nParamsResults, array_keys($nStructResults))) {

            $structResults = array();
            $nodesIntersectFlip = array_flip($nodesIntersect);

            foreach ($nStructResults as $k => $v) {
                if (isset($nodesIntersectFlip[$k])) {
                    $structResults[$k] = $nStructResults[$k];
                }

            }

            $nodesIntersect = array_keys($structResults);
            $nStructResults = $structResults;

        } elseif (isset($nStructResults) && !empty($nStructResults) && !isset($whereQs['whereParamsString'])) {
            $nodesIntersect = array_keys($nStructResults);
        } else {
            $this->queryLog[] = $this->query;
            $this->query = array();

            if ($this->enableCache) {
                XCache::serializedWrite(false, $this->cacheDir, $mark, $this->cacheTimeout);
            }

            return false;
        }
        $this->nodeIntersectAll = $nodesIntersect;
        $this->nodesAllCount = count($nodesIntersect);
        if (isset($this->query['selectCount'])) {

            if ($this->enableCache) {
                XCache::serializedWrite($this->nodesAllCount, $this->cacheDir, $mark, $this->cacheTimeout);
            }

            return $this->nodesAllCount;
        }
        if (!isset($this->query['sortByParam']) && isset($this->query['limit']) && !isset($this->query['alreadyLimited'])) {
            $nodesIntersect = array_slice($nodesIntersect, $this->query['limit'][0], $this->query['limit'][1]);
        }
        if (isset($this->query['delete'])) {
            return $this->deleteProcess($nodesIntersect);
        }
        if (isset($this->query['selectParams'])) {
            if ($this->treeBoosted) {

                $dataI = $this->treeBoost->getMultiById($nodesIntersect);

                foreach ($dataI as $nid => $vid) {
                    $nodesParams[$vid['id']] = $vid['params'];
                }
            } else {
                $marked = md5(print_r($nodesIntersect, true));
                if ($this->enableCache && $ext = XCache::serializedRead($this->cacheDir . '-query-getParams', $marked, $this->cacheTimeout)) {
                    $nodesParams = $ext;
                } else {
                    $paramsResult = $this->getParams($nodesIntersect);
                    while ($pf = $paramsResult->fetch(\PDO::FETCH_ASSOC)) {
                        $nodesParams[$pf['node_name']][$pf['parameter']] = $pf['value'];
                    }
                    if ($this->enableCache) {
                        XCache::serializedWrite($nodesParams, $this->cacheDir . '-query-getParams', $marked, $this->cacheTimeout);
                    }
                }
            }
        } else {
            $nodesParams = null;
        }
        if (isset($nodesIntersect)) {
            if (isset($this->query['sortByParam'])) {
                $nSortResults = $this->sortByParam(array_keys($nStructResults));
                if (!empty($nSortResults)) {

                    $newNodeIntersect = array();

                    foreach ($nSortResults as $k) /// ?? $nodesIntersect
                    {
                        if (!isset($sorted[$k])) {
                            $sorted[$k] = $nStructResults[$k];
                            if (in_array($k, $nodesIntersect)) {
                                $newNodeIntersect[] = $k;
                            }
                        }
                    }
                } else {
                    $sorted = $nStructResults;
                    $newNodeIntersect = $nodesIntersect;
                }
                if ($shortenedIntersection = array_diff($nodesIntersect, $newNodeIntersect)) {
                    $newNodeIntersect = $newNodeIntersect + $shortenedIntersection;
                    foreach ($shortenedIntersection as $k) {
                        $sorted[$k] = $nStructResults[$k];
                    }
                }
                $nodesIntersect = $newNodeIntersect;
                $nodesIntersect = array_slice($nodesIntersect, $this->query['limit'][0], $this->query['limit'][1]);
                $nStructResults = $sorted;
            }
            $result = $this->formatProcess($nStructResults, $nodesParams, $nodesIntersect);
            if ($this->enableCache && !isset($this->query['delete'])) {
                if (!($result instanceOf xteTree)) {
                    $result['nodesAllCount'] = $this->nodesAllCount;
                    $result['nodeIntersectAll'] = $this->nodeIntersectAll;
                }

                if ($this->enableCache) {
                    XCache::serializedWrite($result, $this->cacheDir, $mark, $this->cacheTimeout);
                }

                $endTime = \Common::getmicrotime() - $startTime;
                if ($this->cacheQueryLongerThan < $endTime) {
                    $markQuery['treeName'] = $this->treeName;
                    if ($this->enableCache) {
                        XCache::serializedWrite($markQuery, 'longQuery', md5(print_r($markQuery, true)), $this->cacheTimeout);
                    }
                }
                if (!($result instanceOf xteTree)) {
                    unset($result['nodesAllCount']);
                    unset($result['nodeIntersectAll']);
                }
            }
            $this->queryLog[] = $this->query;
            unset($this->query);
            return $result;
        }
    }

    private function getSelectQueryString()
    {
        if ((isset($this->query['selectStruct']) && is_string($this->query['selectStruct']) && $this->query['selectStruct'] == '*') or isset($this->query['basicpath']) or isset($this->query['paramPath'])) {
            $selectStructString = '*';
        } elseif (isset($this->query['selectStruct']) && is_array($this->query['selectStruct'])) {
            if (array_search('id', $this->query['selectStruct']) === false) {
                array_push($this->query['selectStruct'], 'id');
            }
            $this->query['selectVirtualStruct'] = array_diff($this->query['selectStruct'], $this->nativeStructFieldsList);
            $this->query['selectStruct'] = array_intersect($this->nativeStructFieldsList, $this->query['selectStruct']);
            $selectStruct = $this->query['selectStruct'];
            if (count($this->query['selectVirtualStruct']) > 0) {
                for ($i = 1; $i < $this->levels + 1; $i++)
                    $selectStruct[] = 'x' . $i;
            }
            $selectStructString = implode(',', $selectStruct);
        }
        return array(
            'selectStructString' => isset($selectStructString) ? $selectStructString : null
        );
    }

    private function getWhereQueryString()
    {
        if (isset($this->query['childsAncestor'])) {

            $stopStr = '';
            $sql = array();
            $whereStruct = array();

            if (is_array($this->query['childsAncestor'])) {
                for ($level = 1; $level < ($this->levels); $level++) {
                    if ($this->query['childsLevel']) {
                        if (($stopLevel = $level + $this->query['childsLevel']) >= $this->levels) {
                            $stopLevel = $this->levels;
                        }
                        $stopStr = 'AND x' . ($stopLevel) . ' IS NULL';
                    }
                    $sql[] = '( x' . $level . ' in ("' . implode($this->query['childsAncestor'], '","') . '") ' . $stopStr . ' )';
                }
                $sql = '(' . implode($sql, ' or ') . ')';
            } else {
                $ancestor = $this->getNodeStruct((int)$this->query['childsAncestor']);
                $level = count($ancestor['path']);
                $sql = 'x' . ($level + 1) . ' = ' . (int)$this->query['childsAncestor'];
                if ($this->query['childsLevel']) {
                    $endlevel = $level + $this->query['childsLevel'] + 1;
                    $sql .= ' and x' . $endlevel . '  IS NULL';
                }
            }
            $whereStruct[] = $sql;
        }

        if (empty($this->query['getDisabled'])) {
            $whereStruct[] = ' disabled=0 ';
        }

        $whereParams = array();
        $this->getWhereAnalysis();
        if (isset($this->query['where']) && is_array($this->query['where']) && (count($this->query['where']) > 0)) {
            foreach ($this->query['where'] as $pairs) {
                // struct var
                if (strpos($pairs[0], '@') === 0) {
                    $pairs[0] = substr($pairs[0], 1);

                    if (!$this->query['preventSingleResult'] && $pairs[0] == 'id' && !is_array($pairs[2]) && $pairs[1] == '=') {
                        $this->query['singleResult'] = true;
                    }

                    if ($pairs[0] == 'ancestor') {
                        $ancestor = $this->getNodeStruct($pairs[2]);
                        $level = count($ancestor['path']) + 1;
                        $pairs[0] = 'x' . $level;
                        $whereStruct[] = ' x' . ($level + 1) . ' IS NULL ';
                    }
                    if ($pairs[0] == 'inpath') {

                        if (is_array($pairs[2])) {
                            $addInpath = ' IN ("' . implode('","', $pairs[2]) . '")';
                        } else {
                            $addInpath = '=' . $pairs[2];
                        }
                        for ($i = 1; $i < $this->levels; $i++)
                            $md[] = ' x' . ($i) . $addInpath;
                        $whereStruct[] = '(' . implode(' OR ', $md) . ')';
                        continue;
                    }
                    if (is_array($pairs[2]) and ($pairs[1] == '=')) {

                        $whereStruct[] = $pairs[0] . ' IN ("' . implode('","', $pairs[2]) . '")';
                    } else {
                        if (!is_numeric($pairs[2])) {
                            $pairs[2] = "'{$pairs[2]}'";
                        }
                        $whereStruct[] = $pairs[0] . ' ' . $pairs[1] . ' ' . $pairs[2];
                    }
                } else {
                    if (is_array($pairs[2]) and ($pairs[1] == '=')) {
                        $whereParams[] = "(parameter = '{$pairs[0]}'  AND value IN (\"" . implode('","', $pairs[2]) . '"))';
                    } elseif (is_array($pairs[2]) and ($pairs[1] == 'like')) {

                        if(count($pairs[2])>1) {
                            $imp = implode('|', $pairs[2]);
                            $imp = str_replace('%', '', $imp);
                            $valueSet="REGEXP '{$imp}'";
                        }else{
                                $imp = str_replace('%', '', $pairs[2][0]);
                                $valueSet="like '%\"{$imp}\"%'";
                        }

                        $whereParams[] = "(parameter = '{$pairs[0]}'  AND value {$valueSet})";

                    } elseif (is_array($pairs[2]) and ($pairs[1] == 'alike')) {

                        foreach ($pairs[2] as $pairOne) {
                            $logics[] = " AND value like '%{$pairOne}%' ";
                        }

                        $imp = implode($logics, ' ');

                        $whereParams[] = "(parameter = '{$pairs[0]}'  {$imp} ) ";


                    } elseif ($pairs[3]) {
                        $whereParams[] = $pairs[3];
                    } else {
                        if (!is_numeric($pairs[2])) {
                            $pairs[2] = "'{$pairs[2]}'";
                        }
                        $whereParams[] = "(parameter = '{$pairs[0]}'  AND value " . $pairs[1] . " {$pairs[2]})";
                    }
                }
            }
        }

        $result = array();

        if (isset($whereStruct)) {
            $result['whereStructString'] = implode(' AND ', $whereStruct);
        }
        if (!empty($whereParams))
            $result['whereParamsString'] = implode(' OR ', $whereParams) . ' GROUP BY b.node_name HAVING CCOUNT =' . count($whereParams);


        return $result;
    }

    /**
     * Получить информацию о структуре ноды
     * @param mixed $id
     */
    public function getNodeStruct($id)
    {
        if (!isset($this->nodeCacheStruct[$id])) {
            if ($this->treeBoosted) {
                $node = $this->treeBoost->getById($id, $this->treeStructName);

                unset($node['params']);
                return $this->nodeCacheStruct[$id] = $node;
            }
            $sql = 'SELECT * FROM `' . $this->treeStructName . '` WHERE id =' . $id . ' LIMIT 1';
            if ($pdoResult = $this->PDO->query($sql)) {
                return $this->nodeCacheStruct[$id] = $this->nodeResult($pdoResult->fetch(\PDO::FETCH_ASSOC), true);
            }
        } else {
            return $this->nodeCacheStruct[$id];
        }
    }

    private function nodeResult($pdoResult, $locCall = false, $jsonDecode = false)
    {
        if ((isset($this->query['selectStruct']) and ($this->query['selectStruct'] == '*')) or ($locCall)) {
            $nRes = array(
                'id' => $pdoResult['id'],
                'obj_type' => $pdoResult['obj_type'],
                'basic' => $pdoResult['basic'],
                'disabled' => $pdoResult['disabled'],
                'rate' => $pdoResult['rate'],
                'netid' => $pdoResult['netid']
            );
            if ($jsonDecode) {
                foreach ($pdoResult['params'] as &$param) {
                    if (strstr($param, '["') or strstr($param, '{"'))
                        $param = json_decode($param, true);
                }
            }
            if (isset($pdoResult['params']))
                $nRes['params'] = $pdoResult['params'];
            unset($pdoResult['id'], $pdoResult['params']);
            if (is_array($pdoResult)) {
                $levelsPath = array_splice($pdoResult, $this->levelOffset, 1 + $this->levels);
                $nRes['path'] = $levelsPath = array_values(array_filter($levelsPath));
                if ((isset($this->query['basicpath']) && $levelsPath) or (isset($this->query['paramPath']) && !empty($levelsPath))) {

                    if (!empty($this->query['pathCache'])) {
                        $this->query['pathCache'] = array_merge($this->query['pathCache'], $levelsPath);
                        $this->query['pathCache'] = array_unique($this->query['pathCache']);
                    } elseif (!empty($levelsPath)) {
                        $this->query['pathCache'] = array_unique($levelsPath);
                    }
                }
                $nRes['ancestor'] = end($levelsPath);
                $nRes['ancestorLevel'] = count($levelsPath);
                return $nRes;
            }
        } else {

            $result = array();

            foreach ($this->query['selectStruct'] as $field_key) {
                $result[$field_key] = $pdoResult[$field_key];
            }
            if (!isset($this->query['showNodeChanged'])) {
                unset($pdoResult['params']['__nodeChanged']);
            }
            if ($jsonDecode) {
                foreach ($pdoResult['params'] as &$param) {
                    $param = json_decode($param, true);
                }
            }
            if (isset($pdoResult['params'])) {
                $result['params'] = $pdoResult['params'];
            }
            unset($pdoResult['params']);
            if (isset($this->query['selectVirtualStruct'])) {
                $levelsPath = array_values(array_filter(array_values(array_splice($pdoResult, count($this->query['selectStruct']), count($this->query['selectStruct']) - 1 + $this->levels))));
                foreach ($this->query['selectVirtualStruct'] as $vStruct)
                    switch ($vStruct) {
                        case 'path':
                            $result['path'] = $levelsPath;
                            $this->query['pathCache'] = array_merge($this->query['pathCache'], $levelsPath);
                            break;
                        case 'ancestor':
                            $result['ancestor'] = end($levelsPath);
                            break;
                        case 'ancestorLevel':
                            $result['ancestorLevel'] = count($levelsPath);
                            break;
                    }
            }
            return $result;
        }
    }

    private function getWhereAnalysis()
    {
        $pairsGroups = array();
        if (isset($this->query['where'])) {
            foreach ($this->query['where'] as $pairs) {
                if (strpos($pairs[0], '@') !== 0) {
                    $pairsGroups[$pairs[0]][] = $pairs;
                } else {
                    $extPairs[] = $pairs;
                }
            }
            if (!empty($pairsGroups)) {
                foreach ($pairsGroups as $pg) {
                    if (count($pg) > 1) {
                        $pairReq = array();
                        foreach ($pg as $pair) {
                            $pairReq[] = "(parameter = '{$pair[0]}'  AND value " . $pair[1] . " {$pair[2]})";
                        }
                        $pg[0][3] = '(' . implode(' AND ', $pairReq) . ')';
                    }
                    $extPairs[] = $pg[0];
                }
            }
            $this->query['where'] = $extPairs;
        }
    }

    private function buildStructQuery($selectQs, $whereQs)
    {
        $queryStr = '';

        if (isset($selectQs['selectStructString'])) {
            $queryStr = 'SELECT ' . $selectQs['selectStructString'] . ' FROM `' . $this->treeStructName . '` a ';
        }
        if (isset($whereQs['whereStructString'])) {
            $queryStr .= ' WHERE ' . $whereQs['whereStructString'];
        }
        if (isset($whereQs['inIdArr'])) {
            if (!isset($whereQs['whereStructString'])) {
                $queryStr .= ' WHERE ';
            } else {
                $queryStr .= ' AND ';
            }


            if (count($whereQs['inIdArr']) > $this->useInLenghToIntersect) {
                $queryStr .= ' id between ' . min($whereQs['inIdArr']) . ' and ' . max($whereQs['inIdArr']) . ' ';
            } else {

                $queryStr .= ' id in ("' . implode('","', $whereQs['inIdArr']) . '")';
            }
        }
        if (isset($this->query['sortByStruct'])) {
            $queryStr .= ' order by ' . $this->query['sortByStruct']['element'] . ' ' . $this->query['sortByStruct']['order'];
        } else {
            $queryStr .= ' order by rate';
        }
        /*       if(!isset($whereQs['whereParamsString'])&&($this->query['limit']))
        {
        $queryStr.=' LIMIT '.$this->query['limit'][0].','.$this->query['limit'][1];
        $this->query['alreadyLimited']=true;
        }
        */
        return $queryStr;
    }

    private function getStructResults($queryStr)
    {
        isset($this->query['intersection']) ? $is = print_r($this->query['intersection'], true) : $is = '';
        $marked = md5($queryStr . $is);

        if ($this->enableCache && $ext = XCache::serializedRead($this->cacheDir . '-query-getStructResults', $marked, $this->cacheTimeout)) {
            return $ext;
        }
        if (!empty($this->query['intersection'])) {

            $queryStrIntersect = 'select * from ' . $this->treeStructName . ' WHERE  id IN ("' . implode('","', $this->query['intersection']) . '")';
            $mark = md5($queryStrIntersect);
            if ($this->enableCache && $ext = XCache::serializedRead($this->cacheDir . '-query-getStructResults', $mark, $this->cacheTimeout)) {
                $nStructResultsIntersected = $ext;
            } else {
                $pdoResult = $this->PDO->query($queryStrIntersect);
                if ($nStructResults = $pdoResult->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC)) {
                    $nStructResultsIntersected = array_map('reset', $nStructResults);
                    if ($this->enableCache) {
                        XCache::serializedWrite($nStructResultsIntersected, $this->cacheDir . '-query-getStructResults', $mark, $this->cacheTimeout);
                    }
                }
            }
        }

        if (!empty($queryStr)) {
            $pdoResult = $this->PDO->query($queryStr);
            if ($nStructResults = $pdoResult->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC)) {
                $nStructResults = array_map('reset', $nStructResults);
                if (isset($nStructResultsIntersected)) {
                    $nStructResults = array_intersect_key($nStructResults, $nStructResultsIntersected);
                }
                if ($this->enableCache) {
                    XCache::serializedWrite($nStructResults, $this->cacheDir . '-query-getStructResults', $marked, $this->cacheTimeout);
                }
                return $nStructResults;
            }
        } elseif (isset($nStructResultsIntersected)) {
            if ($this->enableCache) {
                XCache::serializedWrite($nStructResultsIntersected, $this->cacheDir . '-query-getStructResults', $marked, $this->cacheTimeout);
            }
            return $nStructResultsIntersected;
        }
    }

    private function getParamsResults($whereQs)
    {
        if (isset($whereQs['whereParamsString'])) {
            $mark = \Common::createMark($whereQs['whereParamsString'] . $this->treeParamName);
            if ($this->enableCache && $ext = XCache::serializedRead($this->cacheDir . '-query-getParamsResults', $mark, $this->cacheTimeout)) {
                return $ext;
            } else {
                $queryStr = 'select node_name, count( * ) as CCOUNT from ' . $this->treeParamName . ' b  WHERE ' . $whereQs['whereParamsString'];
                $result = $this->PDO->query($queryStr);
                $ext = $result->fetchAll(\PDO::FETCH_COLUMN, 0);
                if ($this->enableCache) {
                    XCache::serializedWrite($ext, $this->cacheDir . '-query-getParamsResults', $mark, $this->cacheTimeout);
                }
                return $ext;
            }
        }
    }

    private function deleteProcess($nodes)
    {
        $query = 'delete  from ' . $this->treeParamName . ' WHERE  node_name IN ("' . implode('","', $nodes) . '")';
        $this->PDO->exec($query);
        $query = 'delete  from ' . $this->treeStructName . ' WHERE  id IN ("' . implode('","', $nodes) . '")';
        unset($this->query);
        return $this->PDO->exec($query);
    }

    private function getParams($nodes)
    {
        $marked = md5(print_r($nodes, true));
        if ($this->enableCache && $ext = XCache::serializedRead($this->cacheDir . '-query-getParams', $marked, $this->cacheTimeout)) {
            return $ext;
        }

        if (is_array($nodes)) {
            $whereNodes = ' node_name IN ("' . implode('","', $nodes) . '")';
        } else {
            $whereNodes = 'node_name=' . $nodes;
        }
        $selectParams = '';
        if (is_array($this->query['selectParams'])) {
            $selectParams = ' AND parameter IN ("' . implode('","', $this->query['selectParams']) . '")';
        }
        $queryStr = 'select node_name,parameter,value from ' . $this->treeParamName . ' WHERE ' . $whereNodes . $selectParams;


        return $this->PDO->query($queryStr);
    }

    private function sortByParam($nodes)
    {
        if (isset($this->query['sortByParam']['element'])) {
            if (empty($this->query['sortByParam']['order'])) {
                $order = 'asc';
            } else {
                $order = $this->query['sortByParam']['order'];
            }
            if (!empty($this->query['sortByParam']['cast'])) {
                if (strtolower($this->query['sortByParam']['cast']) == 'float') {
                    $incast = 'DECIMAL(10,6)';
                } else {
                    $incast = $this->query['sortByParam']['cast'];
                }
                $cast = 'CAST(value AS ' . $incast . ')';
            } else {
                $cast = 'value';
            }
            $q = 'SELECT node_name FROM ' . $this->treeParamName . '  WHERE `parameter`="' . $this->query['sortByParam']['element'] . '" and node_name in("' . implode('","', $nodes) . '") order by ' . $cast . ' ' . $order;
            $result = $this->PDO->query($q);
            return $result->fetchAll(\PDO::FETCH_COLUMN, 0);
            //multisort
        } elseif (isset($this->query['sortByParam'][0])) {
            foreach ($this->query['sortByParam'] as $el) {
                $params[] = $el['element'];
                switch ($el['order']) {
                    case 'desc':
                        $sortex[$el['element']] = array(
                            3
                        );
                        break;
                    case 'asc':
                        $sortex[$el['element']] = array(
                            4
                        );
                        break;
                }
            }
            $q = 'SELECT node_name,parameter,value FROM ' . $this->treeParamName . '  WHERE `parameter` IN ("' . implode('","', $params) . '") and node_name in("' . implode('","', $nodes) . '") order by node_name;';
            $result = $this->PDO->query($q);
            $paramsAll = $result->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($paramsAll)) {
                $idz = null;
                $stack = array();
                foreach ($paramsAll as $paramsElement) {
                    if ($idz != $paramsElement['node_name']) {
                        $sortStacks[$idz] = $stack;
                        $stack = array();
                        $stack['id'] = $paramsElement['node_name'];
                        $idz = $paramsElement['node_name'];
                    }
                    $stack[$paramsElement['parameter']] = $paramsElement['value'];
                }
                $sortStacks[$idz] = $stack;
                return \XARRAY::arrayMultiSort($sortStacks, $sortex);
            }
        }
    }

    private function formatProcess($structRecords, $paramsRecords = null, $nodeIntersect)
    {
        if (!isset($this->query['format'])) {
            $format = 'normal';
        } else {
            $format = $this->query['format'];
        }
        $funcName = 'format_' . $format;
        $this->query['pathCache'] = array();
        reset($nodeIntersect);

        foreach ($nodeIntersect as $key => &$record) {
            $brecord['id'] = $record;
            $record = $structRecords[$record];
            $record = $brecord + $record;
            if (!empty($paramsRecords[$record['id']])) {
                $record['params'] = $paramsRecords[$record['id']];
                $this->$funcName($record);
            } else {
                $this->$funcName($record);
            }
        }
        $records = $this->recordsFormatCache;
        $this->recordsFormatCache = null;
        if (isset($this->query['basicpath'])) {
            $this->basicPathCalculate($records);
        }
        if (isset($this->query['paramPath'])) {
            $this->paramPathCalculate($records);
        }
        if (isset($this->query['astree'])) {
            return new xteTree($records, (int)$this->query['childsAncestor']);
        }
        if (isset($this->query['singleResult']) && $records) {
            return $records[0];
        }
        return $records;
    }

    private function basicPathCalculate(&$nodes)
    {
        if ($this->query['pathCache']) {
            $query = 'select id,basic  from ' . $this->treeStructName . '  where  `id` in ("' . implode('","', array_unique($this->query['pathCache'])) . '")';
            if ($pdoResult = $this->PDO->query($query)) {
                while ($bsc = $pdoResult->fetch(\PDO::FETCH_ASSOC)) {
                    $basics[$bsc['id']] = $bsc['basic'];
                }
            }

            unset($basics[1]);

            foreach ($nodes as $key => $val) {

                if ($val['path']) {
                    $trigger = false;
                    foreach ($val['path'] as $pathElement) {
                        if ($basics[$pathElement]) {
                            $nodes[$key]['basicPath'][$pathElement] = $basics[$pathElement];
                            if ($trigger) {
                                $nodes[$key]['pointBasicPath'][$pathElement] = $basics[$pathElement];
                            }
                            if (($this->query['pathStartPoint']) && ($this->query['pathStartPoint'] == $pathElement)) {
                                $trigger = true;
                            }
                        }
                    }
                    $nodes[$key]['basicPath'][$val['id']] = $val['basic'];
                    if ($trigger) {
                        $nodes[$key]['pointBasicPath'][$val['id']] = $val['basic'];
                    }
                    if ($this->query['basicpath']['separator']) {
                        $nodes[$key]['basicPathValue'] = implode($this->query['basicpath']['separator'], $nodes[$key]['basicPath']);
                        if (isset($nodes[$key]['pointBasicPath'])) {
                            $nodes[$key]['pointBasicPathValue'] = implode($this->query['basicpath']['separator'], $nodes[$key]['pointBasicPath']);
                        }
                    }
                }
            }
            /*порядок по возрастанию уровня вложенности если не указано другое*/
            if (!$this->query['sortByAncestorLevel']) {
                $nodes = \XARRAY::sortByField($nodes, 'ancestorLevel');
            }
        }
    }

    private function paramPathCalculate(&$nodes)
    {
        if ($this->query['pathCache']) {
            $query = 'select node_name,value from ' . $this->treeParamName . '  where `parameter`="' . $this->query['paramPathValue'] . '" and `node_name` in ("' . implode('","', $this->query['pathCache']) . '")';
            if ($pdoResult = $this->PDO->query($query)) {
                while ($prm = $pdoResult->fetch(\PDO::FETCH_ASSOC)) {
                    $prms[$prm['node_name']] = $prm['value'];
                }
            }

            foreach ($nodes as $key => $val) {
                if ($val['path']) {
                    foreach ($val['path'] as $pathElement) {
                        if ($prms[$pathElement])
                            $nodes[$key]['paramPath'][$pathElement] = $prms[$pathElement];
                    }
                    $nodes[$key]['paramPath'][$val['id']] = $val['params'][$this->query['paramPathValue']];
                    if ($this->query['paramPath']['separator']) {
                        $nodes[$key]['paramPathValue'] = implode($this->query['paramPath']['separator'], $nodes[$key]['paramPath']);
                    }
                }
            }

            /*порядок по возрастанию уровня вложенности если не указано другое*/

            if (!$this->query['sortByAncestorLevel']) {
                $nodes = \XARRAY::sortByField($nodes, 'ancestorLevel');
            }
        }
    }

    public function limit($start, $offset)
    {
        $this->query['limit'] = array(
            (int)$start,
            $offset
        );
        return $this;
    }

    public function sortby($el, $order, $cast = '')
    {
        if ((strpos($el, '@') === 0) && (in_array($el = substr($el, 1), $this->nativeStructFieldsList))) {
            $this->query['sortByStruct'] = array(
                'element' => $el,
                'order' => $order,
                'cast' => $cast
            );
        } elseif (empty($this->query['sortByStruct']) && empty($this->query['sortByParam'])) {
            $this->query['sortByParam'] = array(
                'element' => $el,
                'order' => $order,
                'cast' => $cast
            );
        } //multidim sorting
        elseif (empty($this->query['sortByStruct']) && !empty($this->query['sortByParam'])) {
            if (isset($this->query['sortByParam']['element'])) {
                $temp = $this->query['sortByParam'];
                $this->query['sortByParam'] = array();
                $this->query['sortByParam'][] = $temp;
            }
            $this->query['sortByParam'][] = array(
                'element' => $el,
                'order' => $order,
                'cast' => $cast
            );
        }
        return $this;
    }


    /**
     * выбрать дочерние элементы
     *
     * @param int $ancestor -нода у которой необходимо выбрать дочерние
     * @param int $level - сколько уровней выбрать, при $level==0 выбор во всю глубину дерева
     * @return XTreeEngine
     */
    public function childs($ancestor, $level = 0)
    {

        $this->query['childsAncestor'] = $ancestor;
        $this->query['childsLevel'] = $level;
        return $this;
    }

    /**
     * Выбрать структурные данные
     *
     * @param mixed $selectFieldStruct - массив структурных параметров например array('id','obj_type'), либо '*' - все параметры
     * @return XTreeEngine
     */
    public function selectStruct($selectFieldStruct)
    {
        if (is_array($selectFieldStruct) or $selectFieldStruct == '*') {
            $this->query['selectStruct'] = $selectFieldStruct;
            return $this;
        } else {
            trigger_error('selectStruct params error - must be array or *', E_USER_ERROR);
            return $this;
        }
    }

    public function selectParams($selectFieldParams)
    {
        $this->query['selectParams'] = $selectFieldParams;
        return $this;
    }


    public function selectAll()
    {
        $this->selectStruct('*')->selectParams('*');
        return $this;
    }


    public function repoExport($objId, $params = array())
    {
        $data = array();
        if ($params['childMode']) {
            $data['childsData'] = $this->selectParams('*')->selectStruct('*')->childs($objId)->asTree()->run();
        }
        $data['node'] = $this->getNodeInfo($objId);
        return $data;
    }

    /**
     * выбрать в виде дерева
     * результат запроса будет в виде объекта-экземпляра xteTree
     */
    public function asTree()
    {
        $this->query['astree'] = true;
        return $this;
    }

    /**
     * Получить полную информацию о ноде
     * @param mixed $id
     */
    public function getNodeInfo($id, $jsonDecode = false)
    {
        if (isset($id)) {

            $id = (int)$id;

            if ($this->enableCache && $ext = XCache::serializedRead($this->cacheDir . '-node-info', $id, $this->cacheTimeout)) {
                return $this->nodeCache[$id] = $ext;
            }

            if (!isset($this->nodeCache[$id])) {

                if ($this->treeBoosted) {
                    return $this->nodeCache[$id] = $this->treeBoost->getById($id, $this->treeStructName);
                }

                $query = 'SELECT a. * , b.parameter, b.value FROM `' . $this->treeStructName . '` a ,`' . $this->treeParamName . '` b where a.id = b.node_name  and a.id = ' . $id;
                if ($pdoResult = $this->PDO->query($query)) {
                    if ($row = $pdoResult->fetch(\PDO::FETCH_ASSOC)) {
                        $cacherow = $ext = $row;
                        unset($cacherow['value']);
                        unset($cacherow['parameter']);
                        if ($this->enableCache) {
                            $this->nodeCacheStruct[$id] = $this->nodeResult($cacherow, true);
                        }
                        $ext['params'][$row['parameter']] = $row['value'];
                        unset($ext['parameter'], $ext['value']);
                        while ($row = $pdoResult->fetch(\PDO::FETCH_ASSOC)) {
                            $ext['params'][$row['parameter']] = $row['value'];
                        }
                        return $this->nodeCache[$id] = $this->nodeResult($ext, true, $jsonDecode);
                    }
                }
            } else {
                return $this->nodeCache[$id];
            }
        }
    }

    public function repoImport()
    {
    }

    /**
     * Сменить предка - перенос ноды в другую точку дерева
     *
     * @param mixed $nodename индефикатор(id) ноды  - первичная нода
     * @param mixed $newancestor - предок к которому должны быть присоединена нода
     */
    public function changeAncestor($nodename, $newancestor, $relative = null)
    {
        $ids = $this->selectStruct(array(
            'rate',
            'id',
            'path',
            'ancestor',
            'obj_type',
            'disabled',
            'ancestorLevel'
        ))->where(array(
            '@id',
            '=',
            array(
                $nodename,
                $newancestor
            )
        ))->format('keyval', 'id')->run();
        if ((count($ids) == 2) and ($newancestor != $nodename)) {

            $pathUpdateLine = array();
            $extraPathUpdateLine = array();

            //предотвращаем дублирование вариант несовместимых типов
            if (!$this->checkAncestorType($ids[$newancestor]['obj_type'], $ids[$nodename]['obj_type']))
                throw new \Exception('inheritnace-non-compitable-types');
            $this->checkBasic($newancestor, $ids[$nodename]['basic']);

            //переносим ноду

            $dvz = $ids[$nodename]['ancestorLevel'] - $ids[$newancestor]['ancestorLevel'];
            foreach ($ids[$newancestor]['path'] as $pid => $pval) {
                $pathUpdateLine[] = 'x' . ($pid + 1) . "=$pval";
            }
            $pathUpdateLine[] = 'x' . ($ids[$newancestor]['ancestorLevel'] + 1) . "={$ids[$newancestor][id]}";
            $xCount = count($pathUpdateLine);
            for ($i = $xCount; $i < ($this->levels - abs($dvz)); $i++) {
                $extraPathUpdateLine[] = 'x' . ($i + 1) . '=x' . ($i + $dvz);
            }
            if ($dvz < 1)
                $extraPathUpdateLine = array_reverse($extraPathUpdateLine);

            $pathUpdateLine = array_merge($extraPathUpdateLine, $pathUpdateLine);

            $whereLine = 'x' . ($ids[$nodename]['ancestorLevel'] + 1) . '=' . $ids[$nodename]['id'];
            $query = "update `{$this->treeStructName}` SET " . implode(' , ', $pathUpdateLine) . " WHERE ($whereLine) or id=$nodename";
            return $this->PDO->query($query);
        }
    }

    public function format($format = 'normal')
    {
        if (!method_exists($this, 'format_' . $format)) {
            trigger_error('defined format' . $format . ' is not exist');
        }
        $this->query['format'] = $format;
        if (count($args = func_get_args()) > 1) {
            $this->query['formatParams'] = array_slice($args, 1);
        }
        return $this;
    }

    /**
     *      where - метод устанавливающий условие выборки , работающий по принципу SQL оператора WHERE:
     *      может принимать неограниченное количество аргументов - каждый аргумент относиться к последующему-логика "AND"
     *     -знак '@' устанавливается только перед struct параметрами (например @id)
     *      все параметры передаются последовательно в виде массивов.
     *
     *      в случае передачи вторым параметром ===true, первый параметер может быть полным where массивом
     *
     *      $this->_tree->selectStruct('*')->childs(1)->where(array('@obj_type','=','_MODULE'))->run();
     *      вхождение в список значений:
     *      $this->_tree->selectStruct('*')->childs(1)->where(array('@obj_type','=',array('_MODULE','_GROUP'))->run();
     *
     *
     **/
    public function where()
    {
        $arg_list = func_get_args();
        if (($arg_list[1] === true)) {
            if ($arg_list[0])
                $this->query['where'] = $arg_list[0];
        } else {
            $this->query['where'] = $arg_list;
        }
        return $this;
    }

    public function checkAncestorType($ancObjType, $nodeObjType)
    {
        if (array_search($ancObjType, $this->lockObjType[$nodeObjType]) !== false) {
            return true;
        }
    }

    /***
     * Существует ли basic у данного предка
     *
     * @param mixed $ancestor
     * @param mixed $basic может быть массивом либо строка содержащая basic
     */
    private function checkBasic($ancestor, $basic)
    {
        if (!$this->preventBasicCheck) {
            if ($this->uniqType == self::$UNIQ_ANCESTOR) {
                if ($id = $this->selectStruct(array(
                    'id'
                ))->where(array(
                    '@ancestor',
                    '=',
                    $ancestor
                ), array(
                    '@basic',
                    '=',
                    $basic
                ))->run()
                ) {
                    $this->lastNonUniqId = $id[0]['id'];
                    throw new \Exception('non-uniq-ancestor');
                }
            } elseif ($this->uniqType == self::$UNIQ_TREE) {
                if ($id = $this->selectStruct(array(
                    'id'
                ))->where(array(
                    '@basic',
                    '=',
                    $basic
                ))->run()
                ) {
                    $this->lastNonUniqId = $id[0]['id'];
                    throw new \Exception('non-uniq-tree');
                }
            }
        }
        return true;
    }

    /**
     * Получить последующую ноду относительно указанной
     *
     * @param mixed $id индефикатор ноды
     * @param mixed $n необязательный параметр отступа, например взять следующую ноду через 2 относительно указанной
     * @return mixed
     */
    public function getNext($id, $n = 1)
    {
        return $this->getPrev($id, $n, '>');
    }

    /**
     * Получить предыдущую ноду относительно указанной
     *
     * @param mixed $id индефикатор ноды
     * @param mixed $n необязательный параметр отступа, например взять предыдущую ноду через 2 относительно указанной
     * @param mixed $rev - внутренный параметр инверсии отступа
     * @return mixed
     */
    public function getPrev($id, $n = 1, $rev = '<')
    {
        $id = $this->getNodeStruct($id);
        if ($rev == '<') {
            $order = 'desc';
        } else {
            $order = 'asc';
        }
        $nxt = $id[ancestorLevel] + 1;
        $query = 'select id  from ' . $this->treeStructName . " where `x{$id['ancestorLevel']}`='{$id['ancestor']}'  and  `x$nxt` is NULL and rate" . $rev . $id['rate'] . ' order by rate ' . $order . ' limit ' . ($n - 1) . ',1';
        if ($pdoResult = $this->PDO->query($query)) {
            $row = $pdoResult->fetch(\PDO::FETCH_NUM);
            return $row[0];
        } else {
            return null;
        }
    }

    /**
     * Получить но по пути basic элементов
     * @param mixed $node
     */
    public function idByBasicPath($path, $objType = null, $rootInclude = null, $pointPath = null)
    {

        if (!$path)
            return;
        $marked = \Common::createMark($path, $objType, $rootInclude, $pointPath);
        if ($this->enableCache && $ext = XCache::serializedRead($this->cacheDir, $marked, $this->cacheTimeout)) {
            return $ext;
        }
        $xcount = count($path);
        $where = '';
        $k = 0;
        $objectList = '';

        if ($pointPath) {
            $xcount += count($pointPath);
            foreach ($pointPath as $pid => $pval) {
                $pathLine[] = 'x' . ($pid + 1) . "=$pval";
            }
            $where = implode(' and ', $pathLine) . ' and ';
            $k = 1;
            $basics = array_combine($pointPath, $pointPath);
        }
        $where .= 'x' . ($xcount + 1) . ' is NULL';
        if ($rootInclude) {
            array_unshift($objType, '_ROOT');
            array_unshift($path, '%0%');
        }
        if ($objType) {
            $objectList = ' AND  `obj_type` in ("' . implode('","', $objType) . '")';
        }
        $query = 'select *  from ' . $this->treeStructName . '  where ' . $where . ' and `basic` in ("' . implode('","', $path) . '")' . $objectList;

        $gypot = array();

        if ($pdoResult = $this->PDO->query($query)) {
            while ($bsc = $pdoResult->fetch(\PDO::FETCH_ASSOC)) {
                $levels = array_splice($bsc, $this->levelOffset + 1, 1 + $this->levels);
                $bsc['path'] = array_values(array_filter($levels));
                if (count($bsc['path']) == ($xcount - $k)) {
                    $p = $bsc['path'];
                    if ($pointPath) {
                        $p = array_slice($p, count($pointPath));
                    }
                    $bsc['gypo'] = '$' . implode('$', $p) . '$' . $bsc['basic'] . '$';
                    $gypot[] = $bsc;
                } else {
                    $basics[$bsc['id']] = $bsc['basic'];
                }
            }
            if (isset($gypot)) {
                foreach ($basics as $kb => &$basic) {
                    $basicKeys[] = '$' . $kb . '$';
                    $basicValues[] = '$' . $basic . '$';
                }
                if (count($path) > 1) {
                    $gypoTest = implode('$', $path);
                } else {
                    $path = array_values($path);
                    $gypoTest = '$' . $path[0];
                }
                foreach ($gypot as $gyp) {
                    $gypoSearch = str_replace($basicKeys, $basicValues, $gyp['gypo']);
                    if ($gypoSearch == '$' . $gypoTest . '$') {
                        if ($this->enableCache)
                            XCache::serializedWrite($gyp, $this->cacheDir, $marked, $this->cacheTimeout);
                        return $gyp;
                    }
                }
            }
        }
    }

    public function writeNodeParam($nodeName = "", $param, $value)
    {
        if (empty($nodeName)) {
            throw new \Exception('node-id-is-empty');
        }
        $query = "select id,value,parameter from `$this->treeParamName` where `parameter`='$param'  and `node_name`='$nodeName'";
        $result = $this->PDO->query($query);
        $m = $result->fetch(\PDO::FETCH_ASSOC);
        if (!$id = $m['id']) {
            $id = 'NULL';
        }
        if ($id == 'NULL') {
            $query = "insert into `$this->treeParamName` (`id` , `node_name` , `parameter` , `value`) values ($id, '$nodeName', :param, :val) on duplicate key update value=values(value)";
        } else {
            $query = 'update  `' . $this->treeParamName . '`  set `value`=:val where id=' . $id;
        }
        $t = $this->PDO->prepare($query);
        $t->bindParam(":val", $value);

        if ($id == 'NULL') {
            $t->bindParam(":param", $param);
        }

        return $t->execute();
    }

    public function childNodesExist($idNodeArray, $ancestorLevel, $showNodesWithObjType = null)
    {
        $ancestorLevel += 1;
        $nextAncestorLevel = $ancestorLevel + 1;
        if ($showNodesWithObjType)
            $objTypeStr = " `obj_type` in " . '("' . implode('","', $showNodesWithObjType) . '") and ';
        $query = "select id,  `x{$ancestorLevel}` from {$this->treeStructName} where " . $objTypeStr . " `x{$ancestorLevel}` in " . '("' . implode('","', $idNodeArray) . '")' . " and  `x{$nextAncestorLevel}` is NULL";
        $result = $this->PDO->query($query);
        if ($f = $result->fetchAll(\PDO::FETCH_COLUMN, 1)) {
            return array_unique($f);
        }
    }

    public function createRoot($basic = '%0%', $objType = '_ROOT')
    {
        $query = "insert IGNORE into `$this->treeStructName` (`id`,`basic`,`obj_type`,`disabled`,`rate`,`netid`,`x1`) VALUES (1,'$basic','$objType','0','0','0','0')";
        $result = $this->PDO->query($query);
        return $this->PDO->lastInsertId();
    }

    /***
     * Периницилзиция объекта в дереве
     *
     * @param mixed $id предка
     * @param mixed $newbasic имя ноды
     * @param mixed $nodeData данные объекта
     */
    public function reInitTreeObj($id, $newbasic, $nodeData, $objType = null, $netId = false)
    {
        $id = (int)$id;
        if (!$newbasic)
            throw new \Exception('basic-not-provided');
        if ($newbasic !== '%SAME%') {
            $this->setStructData($id, 'basic', $newbasic);
        }
        if (!$objType) {
            $node = $this->getNodeStruct($id);
            $objType = $node['obj_type'];
        }
        if ($netId) {
            $this->setStructData($id, 'netId', $netId);
        }
        $nodeData['__nodeChanged'] = time();
        if (isset($nodeData)) {
            return $this->setTreeObjData($id, $nodeData, $objType);
        }
    }

    public function setStructData($id, $param, $value)
    {
        $query = "UPDATE `$this->treeStructName`  SET `$param` = '$value' WHERE `id` =$id";
        $this->PDO->query($query);
    }

    private function setTreeObjData($id, $data, $objectType)
    {
        $id = (int)$id;
        if ($data) {
            if ($filteredData = $this->filterArrayData($data, $objectType)) {
                return $this->writeNodeParams($id, $filteredData);
            } else {
                return $this->writeNodeParams($id, $data);
            }
        }
    }

    private function filterArrayData($data, $objectType)
    {
        if ($data) {
            if (!$this->filter[$objectType])
                return $data;
            $extKeys = array_intersect(array_keys($data), $this->filter[$objectType]);
            $extKeys[] = '__nodeChanged';
            foreach ($extKeys as $key) {
                $extData[$key] = $data[$key];
            }
            return $extData;
        }
    }

    public function writeNodeParams($nodename, $paramPack)
    {
        if (!is_array($paramPack)) {
            return false;
        }

        $query = "select id,value,parameter from `$this->treeParamName` where `node_name`='$nodename'";
        $result = $this->PDO->query($query);

        while ($m = $result->fetch(\PDO::FETCH_ASSOC)) {
            $exParams[$m['parameter']] = $m;
        }

        $query = "insert into `$this->treeParamName` (`id` , `node_name` , `parameter` , `value`) values";

        foreach ($paramPack as $param => $value) {
            $id = 'NULL';
            if (isset($exParams[$param])) {
                $id = $exParams[$param]['id'];
            }
            //serilize all arrays
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $queryArr[] = "($id, '$nodename', " . $this->PDO->quote($param) . ", " . $this->PDO->quote($value) . ")";
        }
        $query = $query . implode(',', $queryArr) . ' on duplicate key update value=values(value)';
        $r = $this->PDO->exec($query);
        unset($queryArr);
        unset($exParams);
        return $r;

    }

    /***
     * Иницаилизация объекта в дереве
     *
     * @param mixed $ancestor id предка
     * @param mixed $basic имя ноды
     * @param mixed $objType тип объекта
     * @param mixed $nodeData данные объекта
     */
    public function getLastException()
    {
        return array_slice($this->ExceptionHandlers, -1, 1, true);
    }

    public function initTreeObj($ancestor, $basic, $objType, $nodeData = null, $netId = 0, $disabled = 0)
    {
        if (!$ancestor) {
            return;
        }

        $ancestor = (int)$ancestor;

        if ($this->dataPump) {
            try {
                return $this->dataPumper(array(
                    'ancestor' => $ancestor,
                    'basic' => $basic,
                    'objType' => $objType,
                    'data' => $nodeData,
                    'disabled' => $disabled
                ));
            } catch (\Exception $e) {
                $this->ExceptionHandlers[] = $e;
                return false;
            }
        }
        try {
            $id = $this->addBasic($ancestor, $basic, $objType, 'down', $netId, $disabled);
            $nodeData['__nodeChanged'] = time();
            $this->setTreeObjData($id, $nodeData, $objType);
            return $id;
        } catch (\Exception $e) {

            if (!empty($this->capture)) {
                $this->ExceptionHandlers[] = $e;
            }
            return false;
        }
    }

    private function dataPumper($node)
    {

        static $pumpCounter = 0;
        if (!isset($this->lockObjType[$node['objType']]))
            throw new \Exception('parent-objectType-missed');
        if (isset($this->pumpDataDirect[$node['ancestor']]) && ($ancestor = $this->pumpDataDirect[$node['ancestor']])) {
            if (!in_array($this->pumpDataDirect[$node['id']]['objType'], $this->lockObjType[$node['objType']])) {
                throw new \Exception('ancestor-lock-missed');
            } else {
                $node['path'] = $ancestor['path'];
                $node['path'][] = $ancestor['id'];
                $node['rate'] = $node['id'] = $this->dataPumpIncrement++;
                $this->checkBasicPump($node['ancestor'], $node['basic']);
            }
        } else {
            $node['rate'] = $node['id'] = $this->dataPumpIncrement++;
            $this->pumpDataNodeToCheck[$node['id']] = $node['id'];
        }
        $this->pumpDataDirect[$node['id']] = $node;
        $this->pumpBox[] = $this->pumpDataAll[$node['ancestor']][$node['id']] = $node['id'];
        $pumpCounter++;
        if ($this->autoPumpNumber == $pumpCounter) {
            $pumpCounter = 0;
            $this->dataPumpGo();
        }
        return $node['id'];
    }

    private function checkBasicPump($ancestor, $basic)
    {
        foreach ($this->pumpDataAll[$ancestor] as $node) {
            if ($node['basic'] == $basic) {
                throw new \Exception('non-uniq-ancestor');
            }
        }
    }

    public function dataPumpGo()
    {
        static $fullLenghtPath = '';
        if (!empty($this->pumpDataNodeToCheck)) {
            foreach ($this->pumpDataNodeToCheck as $checkNode) {
                $checkNode = $this->pumpDataDirect[$checkNode];
                if ($checkNode['ancestor'] == 1) {
                    $this->pumpDataDirect[$checkNode['id']]['path'] = array(
                        1
                    );
                } else {
                    $ancestors[$checkNode['ancestor']] = true;
                }
                $basics[$checkNode['ancestor']][] = $checkNode['basic'];
            }

            foreach ($basics as $ancestor => $basicTemp) {
                $this->checkBasic($ancestor, $basicTemp);
            }

            if (!empty($ancestors)) {
                $ancestors = array_keys($ancestors);
                $ancestors = $this->selectStruct('*')->where(array(
                    '@id',
                    '=',
                    $ancestors
                ))->run();
                foreach ($ancestors as $ancestor) {
                    if (!empty($this->pumpDataAll[$ancestor['id']])) {
                        foreach ($this->pumpDataAll[$ancestor['id']] as $k => $v) {
                            $this->pumpDataDirect[$k]['path'] = $this->pumpDataDirect[$ancestor['id']]['path'];
                            $this->pumpDataDirect[$k]['path'][] = $ancestor['id'];
                        }
                    }
                    $this->pumpDataDirect[$ancestor['id']] = $ancestor;
                }
            }
        }
        if (!$fullLenghtPath) {
            for ($i = 1; $i <= $this->levels; $i++)
                $xArray[] = 'x' . $i;
            $fullLenghtPath = implode($xArray, '`,`');
        }
        $pumpLine = $this->buildPumpInsertLine();
        $query = "insert into `$this->treeStructName` (`id`,`basic`,`obj_type`,`disabled`,`rate`,`netid`,`" . $fullLenghtPath . "`) VALUES ";
        $query .= $pumpLine['struct'];
        $this->PDO->exec($query);
        $this->pumpDataNodeToCheck = $this->pumpBox = array();
    }

    private function buildPumpInsertLine()
    {
        $pumpLineParams = null;
        $pumpLineStruct = array();
        if (!empty($this->pumpBox)) {
            foreach ($this->pumpBox as $nodeId) {
                $node = $this->pumpDataDirect[$nodeId];
                $xArray = array();
                for ($i = 1; $i < $this->levels + 1; $i++) {
                    $xArray[] = isset($node['path'][$i - 1]) ? $node['path'][$i - 1] : 'NULL';
                }
                //не учтена сетевая связь
                $pumpLineStruct[] = "({$node['id']},'{$node['basic']}','{$node['objType']}','{$node['disabled']}','{$node['rate']}',0,'" . implode("','", $xArray) . "') \r\n";
                if (isset($node['params'])) {
                    foreach ($node['params'] as $param => $value) {
                        $pumpLineParams[] = "(NULL,{$node['id']}','{$param}','{$value}')";
                    }
                    $result['params'] = implode(',', $pumpLineParams);
                }
            }
            $result['struct'] = implode(',', $pumpLineStruct);
            return $result;
        }
    }

    public function addBasic($ancestor = 1, $basic, $objType = '_', $position = 'down', $netId = 0, $disabled = 0)
    {
        if ($state = $this->enableCache)
            $this->cacheState(false);
        $this->checkBasic($ancestor, $basic);
        if (!isset($this->lockObjType[$objType]))
            throw new \Exception('parent-objectType-missed');
        if ($ancestorNode = $this->getNodeStruct($ancestor)) {
            if (!in_array($ancestorNode['obj_type'], $this->lockObjType[$objType])) {
                throw new \Exception('ancestor-lock-missed');
            }
            foreach ($ancestorNode['path'] as $pathKey => $pathElement) {
                $xArray['x' . ($pathKey + 1)] = $pathElement;
            }
            $rate = $this->seed++;
            //temp value
            $xArray['x' . (count($ancestorNode['path']) + 1)] = $ancestor;
            $query = "insert into `$this->treeStructName` (`id`,`basic`,`obj_type`,`disabled`,`rate`,`netid`,`" . implode('`,`', array_keys($xArray)) . "`) VALUES (NULL,'$basic','$objType','$disabled','$rate','$netId','" . implode("','", $xArray) . "')";

            $this->PDO->query($query);

            $lastInserted = $this->PDO->lastInsertId();
            if ($basic == "%SAMEASID%") {
                $query = "update `$this->treeStructName` set basic='$lastInserted' where id=$lastInserted";
                $this->PDO->query($query);
            }
            $this->moveRate($lastInserted, null, $position, true);
            $this->cacheState($state);
            return $lastInserted;
        }
    }

    /**
     * Установка относительной позиции в дереве
     * @param mixed $id перемещаемой ноды  либо массив структуры ноды
     * @param mixed $relative -id релятивной ноды относительно того куда происходит перемещение, если релятив не указан значит нода будет установлена последней
     * в рамках заданного предка
     */
    public function moveRate($id, $relative = null, $position = 'down', $newnode = false)
    {
        static $relativeCache = array();
        if (is_array($id)) {
            $mainStruct = $id;
        } else {
            $mainStruct = $this->getNodeStruct($id);
        }
        if (!$relative) {
            if ($position == 'up') {
                $order = 'ASC';
            } else {
                $order = 'DESC';
            }
            // берем в качестве релятива последнюю ноду
            $query = "select * from  $this->treeStructName where x{$mainStruct['ancestorLevel']}='{$mainStruct['ancestor']}' order by rate $order limit 1";
            $qm = md5($query);
            /*if(!$relativeStruct= $relativeCache[$qm])
            {    
            */
            if ($pdoResult = $this->PDO->query($query)) {
                $relativeCache[$qm] = $relativeStruct = $this->nodeResult($pdoResult->fetch(\PDO::FETCH_ASSOC), true);
            } else {
                return;
            }
            /* }*/
            //перемещение относительно самого себя
            if ($id == $relativeStruct['id'])
                return;
        }
        if (!$relativeStruct)
            $relativeStruct = $this->getNodeStruct($relative);
        switch ($position) {
            case 'up':
                $rate_sign = '>=';
                $newRate = $relativeStruct['rate'];
                break;
            case 'down':
                $rate_sign = '>';
                $newRate = $relativeStruct['rate'] + 1;
                break;
        }
        if ($newnode) {
            $query = "UPDATE `$this->treeStructName` SET `rate` = '$newRate' WHERE `id` = $id LIMIT 1";
            $this->PDO->query($query);
            return;
        } else {
            $nodeInfo = $this->getNodeInfo($id);
            $nodeBasic = $nodeInfo['basic'];
            $query = "UPDATE `$this->treeParamName` SET `value` = '$new_rate' WHERE `node_name` LIKE '$nodeBasic' AND `parameter` LIKE 'rate' LIMIT 1";
            $this->PDO->query($query);
        }

        if ($relativeStruct['ancestor'] != $mainStruct['ancestor']) {
            $query = "update $this->treeStructName  SET rate=rate+1   WHERE 
                      `x{$relativeStruct[ancestorLevel]}`={$relativeStruct[ancestor]}   AND  rate  $rate_sign {$relativeStruct[rate]}";
        } else {
            if ($relativeStruct['rate'] < $mainStruct['rate']) {
                $query = "update $this->treeStructName   SET rate=rate+1  WHERE `x{$relativeStruct[ancestorLevel]}`={$relativeStruct[ancestor]}   AND  rate $rate_sign {$relativeStruct[rate]}
                        AND  {$mainStruct[rate]}  $rate_sign  rate";
            } else {
                /*if ($position == 'up')
                {
                $newRate =$relative_struct['rate'] - 1;
                $rate_sign='>';
                }
                
                */
                $query = "update $this->treeStructName  SET rate=rate+1   WHERE  `x{$relativeStruct[ancestorLevel]}`={$relativeStruct[ancestor]}  AND rate $rate_sign {$relativeStruct[rate]}";
            }
        }
        if ($result = $this->PDO->query($query)) {
            $query = "UPDATE `$this->treeStructName` SET `rate` = '$newRate' WHERE `id` = $id LIMIT 1";
            $this->PDO->query($query);
            return true;
        }
    }

    public function singleResult()
    {
        $this->query['singleResult'] = true;
        return $this;
    }

    /**
     * установить объект в дереве
     *
     * @param mixed $objectType -тип объекта
     * @param mixed $filter -поля объекта
     * @param array $ancestors - массив предков
     */
    public function setObject($objectType, $filter, $ancestors = null)
    {
        $this->filter[$objectType] = $filter;
        $this->lockObjType[$objectType] = $ancestors;
    }

    public function readNodeParam($nodename, $param)
    {

        $marked = md5($nodename . $param);

        if ($this->treeBoosted) {
            $node = $this->treeBoost->getById($nodename, $this->treeStructName);
            return $node['params'][$param];
        }

        if ($this->enableCache) {
            $ext = XCache::serializedRead($this->cacheDir . '-query-readNodeParam', $marked, $this->cacheTimeout);
            if ($ext !== false) {
                return $ext;
            }
        }

        $query = 'select value from ' . $this->treeParamName . " where `parameter`='$param' and node_name='$nodename' limit 1";

        $result = $this->PDO->query($query);
        if ($r = $result->fetchAll(\PDO::FETCH_COLUMN, 0)) {
            if ($this->enableCache) {
                if (empty($r[0])) $r[0] = null;
                XCache::serializedWrite($r[0], $this->cacheDir . '-query-readNodeParam', $marked, $this->cacheTimeout);
            }
            return $r[0];
        }
    }

    public function subCopy($node, $ancestor, $tContext, $extdata)
    {
        if (!self::$maxCounter) {
            self::$maxCounter = $extdata['maxId'];
        }
        self::$maxCounter++;
        $this->innerTree[$node['id']] = array(
            'id' => self::$maxCounter
        );
        if ($this->innerTree[$ancestor]) {
            $path = $this->innerTree[$ancestor]['path'];
            $path[] = $this->innerTree[$ancestor]['id'];
        } else {
            $path = $extdata['path'];
            $path[] = $extdata['id'];
        }
        $this->innerTree[$node['id']]['path'] = $path;
    }

    public function dropQuery()
    {
        $this->query = null;
        return $this;
    }

    public function executeQueryArray($query)
    {
        $this->query = $query;
        return $this->run();
    }

    public function selectCount()
    {
        $this->query['selectCount'] = true;
        return $this;
    }

    public function addWhere($where)
    {
        if (isset($where)) {
            if (is_array($where[0])) {
                foreach ($where as $w) {
                    $this->query['where'][] = $w;
                }
            } else {
                $this->query['where'][] = $where;
            }
        }
        return $this;
    }

    /**
     * Выбор пути basic
     *
     * @param mixed $separator сепаратор соединяющий путь  из basic' ов
     * @param mixed $sortByAncestorLevel - порядок по возрастанию уровня вложенности если не указано другое
     * @return XTreeEngine
     */
    public function getBasicPath($separator = '/', $sortByAncestorLevel = false, $pathStartPoint = 1)
    {
        $this->query['sortByAncestorLevel'] = $sortByAncestorLevel;
        $this->query['basicpath'] = array(
            'separator' => $separator
        );
        $this->query['pathStartPoint'] = $pathStartPoint;
        $this->query['selectStruct'] = '*';
        return $this;
    }

    public function showNodeChanged($state = true)
    {
        $this->query['showNodeChanged'] = $state;
        return $this;
    }

    public function getDisabled()
    {
        $this->query['getDisabled'] = true;
        return $this;
    }

    public function getParamPath($param, $separator = '/', $sortByAncestorLevel = false)
    {
        $this->query['sortByAncestorLevel'] = $sortByAncestorLevel;
        $this->query['paramPathValue'] = $param;
        $this->query['selectStruct'] = '*';
        $this->query['selectParams'] = array(
            $param
        );
        $this->query['paramPath'] = array(
            'separator' => $separator
        );
        return $this;
    }

    public function intersectWith($idIntersection)
    {
        if ($idIntersection['noResults'] == true) {
            $this->query['noResults'] = true;
        } elseif (!isset($this->query['intersection'])) {
            $this->query['intersection'] = $idIntersection;
        } else {
            $this->query['intersection'] = array_intersect($this->query['intersection'], $idIntersection);
            if (empty($this->query['intersection'])) {
                $this->query['noResults'] = true;
            }
        }
        return $this;
    }

    public function jsonDecode()
    {
        $this->query['jsonDecode'] = true;
        return $this;
    }

    public function syncNetIdObjects($id, $ancestors = null)
    {

        $this->delete()->where(array('@netid', '=', (int)$id))->run();

        if (!empty($ancestors) && is_array($ancestors)) {
            $copied = array();

            foreach ($ancestors as $ancestor) {
                $copied[] = $this->copyNodes((int)$ancestor, (int)$id);
            }


            foreach ($copied as $cpy) {
                $cpyid = $cpy[key($cpy)]['id'];
                $this->setStructData($cpyid, 'netid', (int)$id);

            }

            return $copied;

        }
    }

    /**
     * удаление выборки
     * $this->_tree->delete()->childs(1)->where(array('@obj_type','=','_MODULE'))->run();
     */
    public function delete()
    {
        $this->query['delete'] = true;
        $this->query['selectStruct'] = array(
            'id'
        );
        $this->query['getDisabled'] = true;
        return $this;
    }

    public function copyNodes($ancestor, $startNode)
    {
        $exNode = $this->getNodeInfo($startNode);
        $ancNode = $this->getNodeInfo($ancestor);

        $nodes = $this->selectParams('*')->selectStruct('*')->childs($startNode)->asTree()->run();
        if ($exNode['id'] == $exNode['basic']) {
            $exNode['basic'] = '%SAMEASID%';
        }
        $added = false;
        while (!$added) {
            try {
                $id = $this->addBasic($ancestor, $exNode['basic'], $exNode['obj_type']);
                $this->setTreeObjData($id, $exNode['params'], $exNode['obj_type']);
                $added = true;
            } catch (\Exception $e) {
                if ($e->getMessage() == 'non-uniq-ancestor') {
                    $exNode['basic'] .= '_copy';
                }
            }
        }
        $lastCopyIdsOldToNew = array();

        $lastCopyIdsOldToNew[$exNode['id']] = $this->getNodeInfo($id);
        $ancNode['path'][] = $ancestor;
        $query = 'select  max(id) from ' . $this->treeStructName;
        $result = $this->PDO->query($query);
        $max = $result->fetchAll(\PDO::FETCH_COLUMN, 0);
        $extdata = array(
            'id' => $id,
            'path' => $ancNode['path'],
            'maxId' => $max[0]
        );
        $sqlLine = array();

        if ($nodes) {
            $this->resetMaxCounter();
            $nodes->recursiveStep($startNode, $this, 'subCopy', $extdata);
            if (isset($this->innerTree)) {
                for ($i = 1; $i < $this->levels + 1; $i++) {
                    $xArray[] = 'x' . $i;
                }
                $query = "insert into `$this->treeStructName` (`id`,`basic`,`obj_type`,`disabled`,`rate`,`netid`,`" . implode('`,`', $xArray) . '`) VALUES';
                $xArray = array();
                foreach ($this->innerTree as $key => $element) {
                    for ($i = 0; $i < $this->levels; $i++) {
                        $xArray[$i] = ($element['path'][$i]) ? $element['path'][$i] : 'NULL';
                    }
                    $sqlLine[] = '(' . $element['id'] . ',"' . $nodes->nodes[$key]['basic'] . '","' . $nodes->nodes[$key]['obj_type'] . '","' . $nodes->nodes[$key]['disabled'] . '","' . $nodes->nodes[$key]['rate'] . '","' . $nodes->nodes[$key]['netid'] . '",' .
                        implode(",", $xArray) . ')';
                }
                $query .= implode(',', $sqlLine);
                $this->PDO->exec($query);
                $query = "insert into `$this->treeParamName` (`node_name`,`parameter`,`value`) VALUES";
                $insertValues = array();
                $z = 0;

                foreach ($nodes->nodes as $node) {
                    $lastCopyIdsOldToNew[$node['id']] = $this->innerTree[$node['id']];
                    if ($newId = $this->innerTree[$node['id']]['id']) {
                        if (!empty($node['params'])) {
                            foreach ($node['params'] as $paramName => $value) {
                                $insertValues[] = $newId;
                                $insertValues[] = $paramName;
                                $insertValues[] = $value;
                                $z++;
                            }
                        }
                    }
                }
                $query .= str_repeat('(?,?,?),', $z);
                $query = substr($query, 0, -1);
                $this->PDO->beginTransaction();
                $stmt = $this->PDO->prepare($query);

                try {
                    $stmt->execute($insertValues);
                } catch (\PDOException $e) {
                    return $e->getMessage();
                }
                $this->PDO->commit();
                $this->innerTree = array();
            }
        }
        return $lastCopyIdsOldToNew;
    }

    public function resetMaxCounter()
    {
        self::$maxCounter = false;
    }

    private function nullPathUpdateLine($arr)
    {
        $nxt = count($arr) + 1;
        for ($i = $nxt; $i <= $this->levels; $i++) {
            $arr[] = 'x' . $i . "=NULL";
        }
        return $arr;
    }

    private function format_normal($record)
    {
        $jsonDecode = null;
        if (isset($this->query['jsonDecode'])) {
            $jsonDecode = $this->query['jsonDecode'];
        }
        $this->recordsFormatCache[] = $this->nodeResult($record, false, $jsonDecode);
    }

    private function format_keyval($record)
    {
        $jsonDecode = null;
        if (isset($this->query['jsonDecode'])) {
            $jsonDecode = $this->query['jsonDecode'];
        }

        $_record = $this->nodeResult($record, false, $jsonDecode);
        $key = $this->query['formatParams'][0];

        if (isset($this->query['formatParams'][1])) {
            $val = $this->query['formatParams'][1];
        }

        if (!empty($val)) {
            $this->recordsFormatCache[$_record[$key]] = $_record[$val];
        } else {
            $this->recordsFormatCache[$_record[$key]] = $_record;
        }
    }

    private function format_paramsval($record)
    {
        $jsonDecode = null;
        if (isset($this->query['jsonDecode']))
            $jsonDecode = $this->query['jsonDecode'];
        $_record = $this->nodeResult($record, false, $jsonDecode);
        $first = $this->query['formatParams'][0];
        $second = $this->query['formatParams'][1];
        $this->recordsFormatCache[$_record['params'][$first]] = $_record[$second];
    }

    private function format_valparams($record)
    {
        $jsonDecode = null;
        if (isset($this->query['jsonDecode']))
            $jsonDecode = $this->query['jsonDecode'];
        $_record = $this->nodeResult($record, false, $jsonDecode);
        $first = $this->query['formatParams'][0];
        $second = $this->query['formatParams'][1];
        $this->recordsFormatCache[$_record[$first]] = $_record['params'][$second];
    }

    private function format_valval($record)
    {
        $jsonDecode = null;
        if (isset($this->query['jsonDecode']))
            $jsonDecode = $this->query['jsonDecode'];
        $_record = $this->nodeResult($record, false, $jsonDecode);
        $first = $this->query['formatParams'][0];
        $second = $this->query['formatParams'][1];
        $this->recordsFormatCache[$_record[$first]] = $_record[$second];
    }

    private function format_paramsparams($record)
    {
        $jsonDecode = null;
        if (isset($this->query['jsonDecode']))
            $jsonDecode = $this->query['jsonDecode'];
        $_record = $this->nodeResult($record, false, $jsonDecode);
        $first = $this->query['formatParams'][0];
        $second = $this->query['formatParams'][1];
        $this->recordsFormatCache[$_record['params'][$second]] = $_record['params'][$second];
    }


    public function clearNonExistedNode()
    {
        $query = "delete b from {$this->treeStructName} a right join {$this->treeParamName} b on (a.id=b.node_name) where a.id is NULL";
        if ($result = $this->PDO->query($query)) {
            return $result;

        }
    }


}

