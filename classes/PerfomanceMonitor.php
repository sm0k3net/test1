<?php
namespace X4\Classes;

class PerfomanceMonitor
{
    public $tableName = 'perfomance';

    public function __construct()
    {

    }


    public function logPageTime($url, $time, $params)
    {
        $hash = md5(print_r($params, true));
        XPDO::insertIN($this->tableName, array('id' => 'NULL', 'time' => $time, 'url' => $url, 'params' => $params, 'hash' => $hash, 'stamp' => time()));

    }

    public function getAvgTime($url = '')
    {
        if ($url) {
            return XPDO::selectIN(' url, AVG(time) as avg_time', $this->tableName, 'url="' . $url . '"', 'group by url');

        } else {

            return XPDO::selectIN('url,COUNT(id) as load_times , AVG(time) as avg_time', $this->tableName, '', 'group by url order by avg_time desc');
        }

    }

    public function getAvgTimeTotal()
    {
        return XPDO::selectIN('AVG(time) as avg_time', $this->tableName);
    }


    public function flush()
    {
        XPDO::query('truncate ' . $this->tableName);
    }


}
