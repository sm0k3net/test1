<?php

use X4\Classes\XNameSpaceHolder;
use X4\Classes\XRegistry;
use X4\Classes\MultiSection;
use X4\Classes\XCache;

class okResult
{
    public $message = 'ok';

    public function __construct($message = null)
    {
        if ($message) {
            $this->message = $message;
        }
    }
}

class badResult
{
    public $message = 'undefined error occured';

    public function __construct($message)
    {
        $this->message = $message;
    }
}

class connector
{
    public $result;
    public $lct;
    public $message;
    public $error;

    public static $stackError;
    public static $stackMessage;

    public function __construct()
    {
        XOAD_Server::allowClasses('connector');
    }

    public static function pushError($msg, $module = 'connector')
    {
        self::$stackError[] = array(
            'message' => $msg,
            'module' => $module,
        );
    }

    public static function pushMessage($msg, $module = 'connector')
    {
        self::$stackMessage[] = array(
            'message' => $msg,
            'module' => $module,
        );
    }

    public function xroute($data)
    {
        if (is_array($data)) {
            $this->result = array();

            $this->lct = array();
            $transmit = '';

            foreach ($data as $namespace => $functions) {
                if (!strstr($namespace, 'plugin.')) {
                    $transmit = 'module.';
                }

                if (!empty($functions)) {
                    foreach ($functions as $fName => $funcParams) {
                        $result = XNameSpaceHolder::call($transmit . $namespace, $fName, $funcParams);
                        if (!empty($result)) {

                            if ($result instanceof okResult) {
                                $this->result[$fName] = true;
                                $this->pushMessage($result->message, $namespace);
                            }

                            if ($result instanceof badResult) {
                                $this->result[$fName] = false;
                                $this->pushError($result->message . ' [function:' . $fName . ']', $namespace);
                            }

                            $this->message = self::$stackMessage;
                            $this->error = self::$stackError;
                        } elseif ($result === null) {
                            self::pushError($namespace . '::' . key($functions) . ' method not found');
                        }
                    }

                    $instance = XNameSpaceHolder::getInstanceSource($transmit . $namespace, $fName);

                    if ((!empty($instance)) && (($instance->result) or ($instance->lct))) {

                        if (empty($this->lct)) {
                            $this->lct = array();
                        }

                        if (empty($instance->lct)) {
                            $instance->lct = array();
                        }

                        $this->result = array_merge_recursive($this->result, $instance->result);
                        $this->lct = array_merge_recursive($this->lct, $instance->lct);

                    }
                }
            }
        }
    }

    public function xoadGetMeta()
    {
        XOAD_Client::privateVariables($this, array(
            'stackError',
            'stackMessage',
        ));

        XOAD_Client::mapMethods($this, array('xroute'));
        XOAD_Client::publicMethods($this, array('xroute'));
    }
}
