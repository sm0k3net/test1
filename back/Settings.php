<?php

namespace X4\AdminBack;

use X4\Classes\XNameSpaceHolder;
use X4\Classes\XRegistry;
use X4\Classes\XTreeEngine;


class Settings extends \x4class
{
    public $_moduleName = 'Settings';
    public $lct;


    public function __construct()
    {
        XNameSpaceHolder::addObjectToNS('module.Settings.back', $this);
    }


    public function getModuleApiAccess($params)
    {

        $modules = \xCore::discoverModules();
        $plugins = array();

        $fullMethodsList = array();
        $analyser = new \Swagger\StaticAnalyser();
        $analysis = new \Swagger\Analysis();
        $processors = \Swagger\Analysis::processors();

        if (!empty($modules)) {

            $APIItems = [];
            foreach ($modules as $module) {

                $fileName = \xConfig::get('PATH', 'MODULES') . $module['name'] . '/';
                $swagger = \Swagger\scan($fileName);

                $data = $swagger->jsonSerialize();

                if (!empty($data->paths)) {
                    foreach ($data->paths as $value) {
                        $value = (array)$value;
                        $apiItem['method'] = key($value);
                        $APIItems[$module['name']]['module'][] = array(
                            'path' => $value[$apiItem['method']]->path,
                            'operationId' => $value[$apiItem['method']]->operationId,
                            'summary' => $value[$apiItem['method']]->summary

                        );
                    }
                }


                if (!empty($module['plugins'])) {
                    $plugins = $plugins + $module['plugins'];
                }
            }

            $this->result['apiItem'] = $APIItems;
        }


        return;

        if (!empty($plugins)) {

            foreach ($plugins as $plugin) {
                try {
                    $obj = \xCore::pluginFactory($plugin['name'] . '.cron');

                } catch (Exception $e) {
                }

                if (!empty($obj)) {
                    $exploded = explode('.', $plugin['name']);

                    if ($methods = $this->getClassMethods($obj, $plugin['name'] . 'Cron', $exploded[0])) {
                        foreach ($methods as $method) {
                            if (!strstr($method, '__construct')) {
                                $fullMethodsList[] = $plugin['name'] . ':' . $method;

                            }
                        }

                    }
                }

            }


        }

        return $fullMethodsList;

    }

}
