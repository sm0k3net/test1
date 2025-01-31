<?php

namespace X4\Classes;
/**
 * Class Paginator
 * @package X4\Classes
 *
 * $objCount,
 * $this->options['chunkSize'],
 * $link,
 * $TMS,
 * $paginame = 'page',
 * $slashIn=false
 * $pageMoveChunk
 */

class Paginator
{
    private $options = array();
    public $currentPage = 0;
    public $moduleId;
    public $module;

    public function __construct($currentPage, $module, $callerModuleId, $TMS)
    {
        $this->currentPage = $currentPage;
        $this->module = $module;
        $this->TMS = $TMS;
        $this->callerModuleId = $callerModuleId;
        $this->options = array(
            'slash' => '/?',
            'paginatorGetName' => 'page',
            'parseRadius' => \xConfig::get('GLOBAL', 'paginatorParseRadius'),
            'chunkSize' => \xConfig::get('GLOBAL', 'paginatorDefaultChunkSize')
        );
    }


    public function setup(array $options)
    {
        $this->options = array_replace_recursive($this->options, $options);
    }

    public function calculateMovePage($moveChunk, $pagesCount)
    {
        if (($moveChunk * 2 + 1) < $pagesCount) {
            $moveChunkAll = $moveChunk * 2 + 1;
        } else {
            $moveChunkAll = $pagesCount;
        }

        $pagesCountCurrent = ceil($this->currentPage / $this->options['chunkSize']) + 1;

        if (($moveChunk + $pagesCountCurrent) < $pagesCount) {
            $movchPagesCount = $moveChunkAll;
            if ($moveChunk + 1 <= $pagesCountCurrent) {
                $pagesCountCurrent -= $moveChunk + 1;
                $movchPagesCount += $pagesCountCurrent;
            } else {
                $pagesCountCurrent = 0;
            }
        } else {
            $movchPagesCount = $pagesCount;
            if ($pagesCount > $moveChunkAll) {
                $pagesCountCurrent = $pagesCount - $moveChunkAll;
            } else {
                $pagesCountCurrent = 0;
            }
        }

        if ($this->currentPage == 0 && ($moveChunk + $pagesCountCurrent > $pagesCount)) {
            $movchPagesCount = $pagesCount;
        }

        return [$movchPagesCount, $pagesCountCurrent];

    }


    public function generatePrefixSuffixData($pagesCount)
    {
        $linker = $this->options['link'];

        if ($tmpData = XRegistry::get('EVM')->fire($this->module . '.paginator:onPrefixSuffixLink', array('instance' => $this, 'moduleId' => $this->moduleId, 'linker' => $linker))) {
            $linker = $tmpData;
        }

//        $linker=$catalog->_commonObj->buildUrlTransformation($link .$slash. $requestParams);
        //       $linker=XRegistry::get('TPA')->reverseRewrite($linker);


        if (strstr($linker, '?')) {
            $paginameStr = '&' . $linker = $this->options['paginatorGetName'];

        } else {

            $paginameStr = '?' . $this->options['paginatorGetName'];
        }

        if (ceil($this->currentPage / $this->options['chunkSize']) < $pagesCount - 1) {
            $pagesNumber = $this->currentPage + $this->options['chunkSize'];
            $props['next_page'] = array('link' => $linker . $paginameStr . '=' . $pagesNumber);
        }

        if ($this->currentPage >= $this->options['chunkSize']) {
            $pagesNumber = $this->currentPage - $this->options['chunkSize'];
            $props['previous_page'] = array('link' => $linker . $paginameStr . '=' . $pagesNumber);

        }

        if (ceil($this->currentPage / $this->options['chunkSize']) > 1) {
            $pagesNumber = 0;
            $props['first_page'] = array('link' => $linker . $paginameStr . '=' . $pagesNumber);
        }

        if (ceil($this->currentPage / $this->options['chunkSize']) < $pagesCount - 2) {
            $pagesNumber = $pagesCount * $this->options['chunkSize'] - $this->options['chunkSize'];
            $props['last_page'] = array('link' => $linker . $paginameStr . '=' . $pagesNumber);

        }

        return $props;

    }

    public function generatePaginatorData($pagesCount)
    {
        $props = array();
        $dotsPrepend = false;
        $dotsAppend = false;

        if ($this->options['pageMoveChunk']) {
            list($pagesRealCount, $pagesCountCurrent) = $this->calculateMovePage($this->options['pageMoveChunk'], $pagesCount);
        } else {
            $pagesCountCurrent = 0;
            $pagesRealCount = $pagesCount;
        }

        $i = $pagesCountCurrent;

        $pInfo = XRegistry::get('TPA')->getRequestActionInfo();

        $requestParams = $pInfo['requestActionQuery'];
        $requestParams = preg_replace('/((&|\?)' . $this->options['paginatorGetName'] . '=[0-9]+)/i', '', $requestParams);
        $requestParamsStr = '';


        if ($pagesRealCount) {

            while ($i < $pagesRealCount) {
                $paginameStr = '';

                $i++;

                $pageNumber = $i;

                $cpage = ($i - 1) * (int)$this->options['chunkSize'];

                if ($cpage == 0) {

                    if (!empty($requestParams)) {
                        $requestParamsStr .= '?' . $requestParams;
                        $flink = $this->options['link'] . '/' . $requestParamsStr;
                    } else {
                        $flink = $this->options['link'];
                    }

                } else {

                    if ($requestParams) {
                        $paginameStr .= '&' . $this->options['paginatorGetName'];
                    } else {
                        $paginameStr = $this->options['paginatorGetName'];
                    }

                    $flink = $this->options['link'] . $this->options['slash'] . $requestParams . $paginameStr . '=' . $cpage;
                }


                $data = array(
                    'link' => $flink,
                    'pnum' => $pageNumber,
                    'start' => $cpage + 1,
                    'end' => $cpage + 1 + $this->options['chunkSize']
                );


                if ($tmpData = XRegistry::get('EVM')->fire($this->module . '.paginator:onPage', array('instance' => $this, 'moduleId' => $this->moduleId, 'data' => $data))) {
                    $data = $tmpData;
                }

                if ($cpage == $this->currentPage) {

                    $props[$i]['one_page_selected'] = $data;

                } else {
                    if (abs($cpage - $this->currentPage) <= $this->options['parseRadius'] * $this->options['chunkSize']                    // в радиусе парсинга
                        || $i == 1                                                                // первая
                        || $i == $pagesRealCount                                             // последняя
                        || ($cpage == $this->options['chunkSize'] && -$cpage + $this->currentPage <= ($this->options['parseRadius'] + 1) * $this->options['chunkSize'])     // 2я, если активна 5я
                        || ($this->options['objCount'] - $cpage <= 2 * $this->options['chunkSize'] && -$this->currentPage + $cpage <= ($this->options['parseRadius'] + 1) * $this->options['chunkSize'])     // то же с другой стороны
                    ) {

                        $props[$i]['one_page'] = $data;

                    } else {
                        if ($cpage < $this->currentPage && !$dotsPrepend) {
                            $props[$i]['dotsPrepend'] = true;
                            $dotsPrepend = true;
                        } else {
                            if ($cpage > $this->currentPage && !$dotsAppend) {
                                $props[$i]['dotsAppend'] = true;
                                $dotsAppend = true;
                            }
                        }

                    }
                }

                $cpage += $this->options['chunkSize'];
            }
        }

        return $props;
    }

    public function render($headlessMode = false)
    {
        $props = array();
        $pagesCount = ceil($this->options['objCount'] / $this->options['chunkSize']);
        $props['pages'] = $this->generatePaginatorData($pagesCount);
        $props['suffix'] = $this->generatePrefixSuffixData($pagesCount);

        if ($headlessMode) {
            return $props;
        } else {
            return $this->htmlRender($props, $pagesCount);
        }

    }


    private function htmlRender($props, $pagesCount)
    {
        $pageLine = '';
        if (count($props['pages']) > 1) {
            foreach ($props['pages'] as $page) {
                $this->TMS->addMassReplace(key($page), $page[key($page)]);
                $pageLine .= $this->TMS->parseSection(key($page));
            }
        }


        if (isset($props['suffix']['next_page'])) {
            $this->TMS->addMassReplace('next_page', $props['suffix']['next_page']);
            $this->TMS->parseSection('next_page', true);
        }

        if (isset($props['suffix']['previous_page'])) {
            $this->TMS->addMassReplace('previous_page', $props['suffix']['previous_page']);
            $this->TMS->parseSection('previous_page', true);
        }

        if (isset($props['suffix']['first_page'])) {
            $this->TMS->addMassReplace('first_page', $props['suffix']['first_page']);
            $this->TMS->parseSection('first_page', true);
        }

        if (isset($props['suffix']['last_page'])) {
            $this->TMS->addMassReplace('last_page', $props['suffix']['last_page']);
            $this->TMS->parseSection('last_page', true);
        }

        $this->TMS->addMassReplace('page_line', array('first_page' => $props['suffix']['first_page']));
        $this->TMS->addMassReplace('page_line', array('last_page' => $props['suffix']['last_page']));


        $this->TMS->addMassReplace('page_line', array(
            'page_line' => $pageLine,
            'pages_count' => $pagesCount,
            'count' => $this->options['objCount']
        ));

        $this->TMS->parseSection('page_line', true);

    }


}