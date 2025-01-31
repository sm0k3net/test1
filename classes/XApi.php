<?php

namespace X4\Classes;

class XApiException extends \Exception
{
    public function __construct($code, $message = null)
    {
        parent::__construct($message, $code);
    }
}

class XApi
{
    public $isIE = false;
    public $method;
    public $data = null;

    private $codes = array(
        '100' => 'Continue',
        '200' => 'OK',
        '201' => 'Created',
        '202' => 'Accepted',
        '203' => 'Non-Authoritative Information',
        '204' => 'No Content',
        '205' => 'Reset Content',
        '206' => 'Partial Content',
        '300' => 'Multiple Choices',
        '301' => 'Moved Permanently',
        '302' => 'Found',
        '303' => 'See Other',
        '304' => 'Not Modified',
        '305' => 'Use Proxy',
        '307' => 'Temporary Redirect',
        '400' => 'Bad Request',
        '401' => 'Unauthorized',
        '402' => 'Payment Required',
        '403' => 'Forbidden',
        '404' => 'Not Found',
        '405' => 'Method Not Allowed',
        '406' => 'Not Acceptable',
        '409' => 'Conflict',
        '410' => 'Gone',
        '411' => 'Length Required',
        '412' => 'Precondition Failed',
        '413' => 'Request Entity Too Large',
        '414' => 'Request-URI Too Long',
        '415' => 'Unsupported Media Type',
        '416' => 'Requested Range Not Satisfiable',
        '417' => 'Expectation Failed',
        '500' => 'Internal Server Error',
        '501' => 'Not Implemented',
        '503' => 'Service Unavailable'
    );


    public function __construct()
    {
        $this->detectIE();
    }

    public function detectIE()
    {

        if (isset($_SERVER['HTTP_USER_AGENT']) && (strpos($_SERVER['HTTP_USER_AGENT'], 'Trident') !== false)) {
            $this->isIE = true;
        }

    }

    public function error500()
    {
        $this->header(500);
    }

    public function header($code)
    {
        $protocol = $_SERVER['SERVER_PROTOCOL'] ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
        header($protocol . ' ' . $code . ' ' . $this->codes[$code]);
    }

    public function error404()
    {
        header($_SERVER["SERVER_PROTOCOL"] . ' 404 Not Found');

    }

    public function route($params)
    {
        if (method_exists($this, $params['api'])) {
            $this->method = $this->getMethod();

            if ($this->method == 'PUT' || $this->method == 'POST' || $this->method == 'PATCH') {
                $this->data = $this->getData();
            }

            return $this->{$params['api']}($params);
        }

    }

    public function getMethod()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $override = isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']) ? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] : (isset($_GET['method']) ? $_GET['method'] : '');
        if ($method == 'POST' && strtoupper($override) == 'PUT') {
            $method = 'PUT';
        } elseif ($method == 'POST' && strtoupper($override) == 'DELETE') {
            $method = 'DELETE';
        } elseif ($method == 'POST' && strtoupper($override) == 'PATCH') {
            $method = 'PATCH';
        }
        return $method;
    }

    public function getData()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        return $data;
    }

    public function auth($login, $password)
    {

        if (!isset($_SERVER['PHP_AUTH_USER'])) {
            header("WWW-Authenticate: Basic realm=\"Private Area\"");
            $this->header(401);
            print "Sorry - you need valid credentials to be granted access!\n";
            exit;
        } else {
            if (($_SERVER['PHP_AUTH_USER'] == $login) && ($_SERVER['PHP_AUTH_PW'] == $password)) {
                return;

            } else {
                header("WWW-Authenticate: Basic realm=\"Private Area\"");
                $this->header(401);
                print "Sorry - you need valid credentials to be granted access!\n";
                exit;
            }
        }
    }

    public function document($params)
    {


        if ($_REQUEST['json']) {


            if (strstr($params['module'], '.')) {

                $path = \xConfig::get('PATH', 'PLUGINS') . $params['module'];

            } else {
                $path = \xConfig::get('PATH', 'MODULES') . $params['module'];
            }

            $swagger = \Swagger\scan($path);
            header('Content-Type: application/json');
            echo $swagger;

        } else {
            $path = \xConfig::get('PATH', 'EXT') . 'swagger-ui';
            $webPath = \xConfig::get('WEBPATH', 'EXT') . 'swagger-ui';
            XRegistry::get('TMS')->addFileSection($path . '/index.html');
            XRegistry::get('TMS')->addMassReplace('swagger', array(
                'path' => $webPath,
                'swaggerJsonSource' => HOST . '~api/json/' . $params['module'] . '?json=1'
            ));

            echo XRegistry::get('TMS')->parseSection('swagger');

        }
    }


    public function json($params)
    {
        $prettyPrint = false;

        if (strpos($params['module'], '.') !== false) {

            $module = \xCore::pluginFactory($params['module'] . '.api.' . $params['api']);

        } else {
            $module = \xCore::moduleFactory($params['module'] . '.api.' . $params['api']);
        }

        $trail = $this->getTrailingParams($params['trailing']);

        if ($trail['prettyPrint']) {
            $prettyPrint = true;
            unset($trail['prettyPrint']);
        }


        if (method_exists($module, $params['action'])) {
            $result = $module->{$params['action']}($trail, $this->data);

            if (is_array($result)) {
                if (!$this->isIE) {

                    header('Content-Type: application/json; charset=utf-8');

                } else {

                    header('Content-Type: text/plain');
                }

                if ($prettyPrint) {

                    return $this->jsonView($result);


                } else {

                    $resultEvm = XRegistry::get('EVM')->fire('XApi:beforeJsonOutput', array('result' => $result, 'trail' => $trail, 'data' => $this->data));

                    if (!empty($resultEvm)) {
                        $result = $resultEvm;
                    }

                    return json_encode($result);
                }
            }
        }
    }

    private function jsonView($json)
    {
        header("Content-Type: text/html; charset=utf-8");
        $out = '<script src="' . \xConfig::get('WEBPATH', 'XJS') . '_core/jquery.min.js"></script>';
        $out .= '<script src="' . \xConfig::get('WEBPATH', 'XJS') . '_components/jq.jsonview/jsonview.js"></script>';
        $out .= '<link href="' . \xConfig::get('WEBPATH', 'XJS') . '_components/jq.jsonview/jsonview.css" rel="stylesheet" />';
        $out .= '<div id="json"></div>';
        $out .= '<script>$(function() {$("#json").JSONView(' . json_encode($json) . ');});</script>';
        return $out;
    }

    private function getTrailingParams($trailingString)
    {
        $data = explode('/', $trailingString);
        $odd = array();
        $even = array();
        $both = array(&$even, &$odd);
        array_walk($data, function ($v, $k) use ($both) {
            $both[$k % 2][] = $v;
        });

        if (is_array($odd) && is_array($even)) {
            return array_combine($even, $odd);
        }

    }


}
