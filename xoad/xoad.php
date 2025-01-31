<?php
if (!defined('XOAD_BASE'))
    {
    define('XOAD_BASE', dirname(__FILE__));
    }

if (!defined('XOAD_SERIALIZER_SKIP_STRING'))
    {
    define('XOAD_SERIALIZER_SKIP_STRING', '<![xoadSerializer:skipString[');
    }

if (!defined('XOAD_CLIENT_METADATA_METHOD_NAME'))
    {
    define('XOAD_CLIENT_METADATA_METHOD_NAME', 'xoadGetMeta');
    }

if (!defined('XOAD_EVENTS_STORAGE_DSN'))
    {
    define('XOAD_EVENTS_STORAGE_DSN', 'File');
    }

if (!defined('XOAD_EVENTS_LIFETIME'))
    {
    define('XOAD_EVENTS_LIFETIME', 60 * 2);
    }

class XOAD_Observer
{
    public function updateObserver($event, $arguments)
    {
        return true;
    }
}

class XOAD_Observable
{
    /**
     *
     * @access    public
     *
     * @return    bool
     *
     */
    public static function addObserver(&$observer, $className = 'XOAD_Observable')
    {
        if (XOAD_Utilities::getType($observer) != 'object') {

            return false;
        }

        if ( ! is_subclass_of($observer, 'XOAD_Observer')) {

            return false;
        }

        if ( ! isset($GLOBALS['_XOAD_OBSERVERS'])) {

            $GLOBALS['_XOAD_OBSERVERS'] = array();
        }

        $globalObservers =& $GLOBALS['_XOAD_OBSERVERS'];

        $className = strtolower($className);

        if ( ! isset($globalObservers[$className])) {

            $globalObservers[$className] = array();
        }

        $globalObservers[$className][] =& $observer;

        return true;
    }

    /**
     *
     * @access    public
     *
     * @return    bool
     *
     */
    public static function notifyObservers($event = 'default', $arg = null, $className = 'XOAD_Observable')
    {
        if (empty($GLOBALS['_XOAD_OBSERVERS'])) {

            return true;
        }

        $globalObservers =& $GLOBALS['_XOAD_OBSERVERS'];

        $className = strtolower($className);

        if (empty($globalObservers[$className])) {

            return true;
        }

        $returnValue = true;

        foreach ($globalObservers[$className] as $observer) {

            $eventValue = $observer->updateObserver($event, $arg);

            if (XOAD_Utilities::getType($eventValue) == 'bool') {

                $returnValue &= $eventValue;
            }
        }

        return $returnValue;
    }
}

class XOAD_Utilities extends XOAD_Observable
{
    /**
     * Checks if an array is an associative array.
     *
     * @access    public
     *
     * @param    mixed    $var    The array to check.
     *
     * @return    bool    true if {@link $var} is an associative array, false
     *                    if {@link $var} is a sequential array.
     *
     * @static
     *
     */
    public static function isAssocArray($var)
    {
        // This code is based on mike-php's
        // comment in is_array function documentation.
        //
        // http://bg.php.net/is_array
        //
        // Thank you.
        //

        if ( ! is_array($var)) {

            return false;
        }

        $arrayKeys = array_keys($var);

        $sequentialKeys = range(0, sizeof($var));

        if (function_exists('array_diff_assoc')) {

            if (array_diff_assoc($arrayKeys, $sequentialKeys)) {

                return true;
            }

        } else {

            if (
            (array_diff($arrayKeys, $sequentialKeys)) &&
            (array_diff($sequentialKeys, $arrayKeys))) {

                return true;
            }
        }

        return false;
    }

    /**
     * Gets the type of a variable.
     *
     * @access    public
     *
     * @param    mixed    $var    The source variable.
     *
     * @return    string    Possibles values for the returned string are:
     *                    - "bool"
     *                    - "int"
     *                    - "float"
     *                    - "string"
     *                    - "s_array"
     *                    - "a_array"
     *                    - "object"
     *                    - "null"
     *                    - "unknown"
     *
     * @static
     *
     */
    public static function getType($var)
    {
        if (is_bool($var)) {

            return 'bool';

        } else if (is_int($var)) {

            return 'int';

        } else if (is_float($var)) {

            return 'float';

        } else if (is_string($var)) {

            return 'string';

        } else if (is_array($var)) {

            if (XOAD_Utilities::isAssocArray($var)) {

                return 'a_array';

            } else {

                return 's_array';
            }

        } else if (is_object($var)) {

            return 'object';

        } else if (is_null($var)) {

            return 'null';
        }

        return 'unknown';
    }

    /**
     * Return current UNIX timestamp with microseconds.
     *
     * @access    public
     *
     * @return    float    Returns the float 'sec,msec' where 'sec' is the
     *                    current time measured in the number of seconds since
     *                    the Unix Epoch (0:00:00 January 1, 1970 GMT), and
     *                    'msec' is the microseconds part.
     *
     * @static
     *
     */
    public static function getMicroTime()
    {
        list($microTime, $time) = explode(" ", microtime());

        return ((float) $microTime + (float) $time);
    }

    /**
     * Returns the URL for the current request (includings
     * the query string).
     *
     * @access    public
     *
     * @return    string    Current request URL.
     *
     * @static
     *
     */
    public static function getRequestUrl()
    {
        $url = $_SERVER['PHP_SELF'];

        if ( ! empty($_SERVER['QUERY_STRING'])) {

            $url .= '?' . $_SERVER['QUERY_STRING'];
        }

        return $url;
    }

    /**
     * Registers XOAD client header files.
     *
     * @access    public
     *
     * @param    string    $base        Base XOAD folder.
     *
     * @param    bool    $optimized    true to include optimized headers, false otherwise.
     *
     * @return    string    HTML code to include XOAD client files.
     *
     * @static
     *
     */
    public static function header($base = '.', $optimized = true)
    {
        $returnValue = '<script type="text/javascript" src="' . $base . '/js/';

        $returnValue .= 'xoad';

        if ($optimized) {

            $returnValue .= '_optimized';
        }

        $returnValue .= '.js"></script>';

        if (array_key_exists('_XOAD_CUSTOM_HEADERS', $GLOBALS)) {

            foreach ($GLOBALS['_XOAD_CUSTOM_HEADERS'] as $fileName) {

                $returnValue .= '<script type="text/javascript" src="' . $base . ($optimized ? $fileName[1] : $fileName[0]) . '"></script>';
            }
        }

        if (array_key_exists('_XOAD_EXTENSION_HEADERS', $GLOBALS)) {

            foreach ($GLOBALS['_XOAD_EXTENSION_HEADERS'] as $extension => $files) {

                $extensionBase = $base . '/extensions/' . $extension . '/';

                foreach ($files as $fileName) {

                    $returnValue .= '<script type="text/javascript" src="' . $extensionBase . ($optimized ? $fileName[1] : $fileName[0]) . '"></script>';
                }
            }
        }

        return $returnValue;
    }

    /**
     * Registers XOAD Events header data.
     *
     * <p>You should call this method after {@link XOAD_Utilities::header}.</p>
     * <p>XOAD Events header data includes server time and callback URL.</p>
     *
     * @access    public
     *
     * @param    string    $callbackUrl    XOAD Events callback URL.
     *
     * @return    string    HTML code to initialize XOAD Events.
     *
     * @static
     *
     */
    public static function eventsHeader($callbackUrl = null)
    {
        if ($callbackUrl == null) {

            $callbackUrl = XOAD_Utilities::getRequestUrl();
        }

        $returnValue = '<script type="text/javascript">';
        $returnValue .= 'xoad.events.callbackUrl = ' . XOAD_Client::register($callbackUrl) . ';';
        $returnValue .= 'xoad.events.lastRefresh = ' . XOAD_Client::register(XOAD_Utilities::getMicroTime()) . ';';
        $returnValue .= '</script>';

        return $returnValue;
    }

    /**
     * Registers XOAD extension client header file.
     *
     * @access    public
     *
     * @param    string    $extension            The name of the XOAD extension.
     *
     * @param    string    $fileName            The extension JavaScript file name.
     *                                        This file must be located in the
     *                                        extension base folder.
     *
     * @param    string    $optimizedFileName    The optimized extension JavaScript file name.
     *                                        This file must be located in the
     *                                        extension base folder.
     *
     *
     * @return    bool    true on success, false otherwise.
     *
     * @static
     *
     */
    public static function extensionHeader($extension, $fileName, $optimizedFileName = null)
    {
        if ( ! array_key_exists('_XOAD_EXTENSION_HEADERS', $GLOBALS)) {

            $GLOBALS['_XOAD_EXTENSION_HEADERS'] = array();
        }

        if ( ! array_key_exists('_XOAD_HEADERS', $GLOBALS)) {

            $GLOBALS['_XOAD_HEADERS'] = array();
        }

        $extension = strtolower($extension);

        if ( ! array_key_exists($extension, $GLOBALS['_XOAD_EXTENSION_HEADERS'])) {

            $GLOBALS['_XOAD_EXTENSION_HEADERS'][$extension] = array();
        }

        if (empty($optimizedFileName)) {

            $optimizedFileName = $fileName;
        }

        if (
        (in_array($fileName, $GLOBALS['_XOAD_HEADERS'])) &&
        (in_array($optimizedFileName, $GLOBALS['_XOAD_HEADERS']))) {

            return false;
        }

        $GLOBALS['_XOAD_EXTENSION_HEADERS'][$extension][] = array($fileName, $optimizedFileName);
        $GLOBALS['_XOAD_HEADERS'][] = $fileName;
        $GLOBALS['_XOAD_HEADERS'][] = $optimizedFileName;

        return true;
    }

    /**
     * Registers custom client header file.
     *
     * @access    public
     *
     * @param    string    $fileName            The JavaScript file name.
     *                                        This file must be located in the
     *                                        base folder.
     *
     * @param    string    $optimizedFileName    The optimized JavaScript file name.
     *                                        This file must be located in the
     *                                        base folder.
     *
     *
     * @return    bool    true on success, false otherwise.
     *
     * @static
     *
     */
    public static function customHeader($fileName, $optimizedFileName = null)
    {
        if ( ! array_key_exists('_XOAD_CUSTOM_HEADERS', $GLOBALS)) {

            $GLOBALS['_XOAD_CUSTOM_HEADERS'] = array();
        }

        if ( ! array_key_exists('_XOAD_HEADERS', $GLOBALS)) {

            $GLOBALS['_XOAD_HEADERS'] = array();
        }

        if (empty($optimizedFileName)) {

            $optimizedFileName = $fileName;
        }

        if (
        (in_array($fileName, $GLOBALS['_XOAD_HEADERS'])) &&
        (in_array($optimizedFileName, $GLOBALS['_XOAD_HEADERS']))) {

            return false;
        }

        $GLOBALS['_XOAD_CUSTOM_HEADERS'][] = array($fileName, $optimizedFileName);
        $GLOBALS['_XOAD_HEADERS'][] = $fileName;
        $GLOBALS['_XOAD_HEADERS'][] = $optimizedFileName;

        return true;
    }

    /**
     * Returns the input string with all alphabetic characters
     * converted to lower or upper case depending on the configuration.
     *
     * @param    string    $text    The text to convert to lower/upper case.
     *
     * @return    string    The converted text.
     *
     * @static
     *
     */
    public static function caseConvert($text)
    {
        return strtolower($text);
    }

    /**
     * Adds a {@link XOAD_Utilities} events observer.
     *
     * @access    public
     *
     * @param    mixed    $observer    The observer object to add (must extend {@link XOAD_Observer}).
     *
     * @return    string    true on success, false otherwise.
     *
     * @static
     *
     */
    public static function addObserver(&$observer, $className = 'XOAD_Utilities')
    {
        return parent::addObserver($observer, $className);
    }

    /**
     *
     * @access    public
     *
     * @return    bool
     *
     */
    public static function notifyObservers($event = 'default', $arg = null, $className = 'XOAD_Utilities')
    {
        return parent::notifyObservers($event, $arg, $className);
    }
}

function utf2win_recursive(&$value, &$key, $userdata = "")
    {
    $value=XOAD_Utilities::utf2win($value);
    }

function array_walk_recursive_xoad(&$input, $funcname, $userdata = "")
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
            array_walk_recursive_xoad($input[$key], $funcname, $userdata);
            }
        else
            {
            $saved_value=$value;
            $saved_key  =$key;
            $funcname($value, $key);

            if ($value != $saved_value || $saved_key != $key)
                {
                $input[$key]=$value;
                }
            }
        }

    return true;
    }
  

class XOAD_Serializer extends XOAD_Observable
{
    /**
     * Serializes a PHP variable into a {@link http://www.json.org JSON} string.
     *
     * <p>Example:</p>
     * <code>
     * <script type="text/javascript">
     * <?php require_once('xoad.php'); ?>
     *
     * var arr = <?= XOAD_Serializer::serialize(array(1, 2, "string", array("Nested"))) ?>;
     *
     * alert(arr);
     *
     * </script>
     * </code>
     *
     * @access    public
     *
     * @param    mixed    $var    Variable to serialize.
     *
     * @return    string    {@link http://www.json.org JSON} string that
     *                    represents the variable.
     *
     * @static
     *
     */
    public static function serialize(&$var)
    {
        $type = XOAD_Utilities::getType($var);

        if ($type == 'bool') {

            if ($var) {

                return "true";

            } else {

                return "false";
            }

        } else if ($type == 'int') {

            return sprintf('%d', $var);

        } else if ($type == 'float') {

            return sprintf('%f', $var);

        } else if ($type == 'string') {

            if (strlen($var) >= strlen(XOAD_SERIALIZER_SKIP_STRING)) {

                if (strcasecmp(substr($var, 0, strlen(XOAD_SERIALIZER_SKIP_STRING)), XOAD_SERIALIZER_SKIP_STRING) == 0) {

                    return substr($var, strlen(XOAD_SERIALIZER_SKIP_STRING), strlen($var) - strlen(XOAD_SERIALIZER_SKIP_STRING));
                }
            }

            // This code is based on morris_hirsch's
            // comment in utf8_decode function documentation.
            //
            // http://bg.php.net/utf8_decode
            //
            // Thank you.
            //

            $ascii = '';

            $length = strlen($var);

            for ($iterator = 0; $iterator < $length; $iterator ++) {

                $char = $var{$iterator};

                $charCode = ord($char);

                if ($charCode == 0x08) {

                    $ascii .= '\b';

                } else if ($charCode == 0x09) {

                    $ascii .= '\t';

                } else if ($charCode == 0x0A) {

                    $ascii .= '\n';

                } else if ($charCode == 0x0C) {

                    $ascii .= '\f';

                } else if ($charCode == 0x0D) {

                    $ascii .= '\r';

                } else if (($charCode == 0x22) || ($charCode == 0x2F) || ($charCode == 0x5C)) {

                    $ascii .= '\\' . $var{$iterator};

                } else if ($charCode < 128) {

                    $ascii .= $char;

                } else if ($charCode >> 5 == 6) {

                    $byteOne = ($charCode & 31);

                    $iterator ++;

                    $char = $var{$iterator};

                    $charCode = ord($char);

                    $byteTwo = ($charCode & 63);

                    $charCode = ($byteOne * 64) + $byteTwo;

                    $ascii .= sprintf('\u%04s', dechex($charCode));

                } else if ($charCode >> 4 == 14) {

                    $byteOne = ($charCode & 31);

                    $iterator ++;

                    $char = $var{$iterator};

                    $charCode = ord($char);

                    $byteTwo = ($charCode & 63);

                    $iterator ++;

                    $char = $var{$iterator};

                    $charCode = ord($char);

                    $byteThree = ($charCode & 63);

                    $charCode = ((($byteOne * 64) + $byteTwo) * 64) + $byteThree;

                    $ascii .= sprintf('\u%04s', dechex($charCode));

                } else if ($charCode >> 3 == 30) {

                    $byteOne = ($charCode & 31);

                    $iterator ++;

                    $char = $var{$iterator};

                    $charCode = ord($char);

                    $byteTwo = ($charCode & 63);

                    $iterator ++;

                    $char = $var{$iterator};

                    $charCode = ord($char);

                    $byteThree = ($charCode & 63);

                    $iterator ++;

                    $char = $var{$iterator};

                    $charCode = ord($char);

                    $byteFour = ($charCode & 63);

                    $charCode = ((((($byteOne * 64) + $byteTwo) * 64) + $byteThree) * 64) + $byteFour;

                    $ascii .= sprintf('\u%04s', dechex($charCode));
                }
            }

            return ('"' . $ascii . '"');

        } else if ($type == 's_array') {

            $index = 0;

            $length = sizeof($var);

            $returnValue = '[';

            foreach ($var as $value) {

                $returnValue .= XOAD_Serializer::serialize($value);

                if ($index < $length - 1) {

                    $returnValue .= ',';
                }

                $index ++;
            }

            $returnValue .= ']';

            return $returnValue;

        } else if ($type == 'a_array') {

            $index = 0;

            $length = sizeof($var);

            $returnValue = '{';

            foreach ($var as $key => $value) {

                $returnValue .= XOAD_Serializer::serialize($key);

                $returnValue .= ':';

                $returnValue .= XOAD_Serializer::serialize($value);

                if ($index < $length - 1) {

                    $returnValue .= ',';
                }

                $index ++;
            }

            $returnValue .= '}';

            return $returnValue;

        } else if ($type == 'object') {

            $objectVars = get_object_vars($var);

            return XOAD_Serializer::serialize($objectVars);
        }

        return "null";
    }

    /**
     * Adds a {@link XOAD_Serializer} events observer.
     *
     * @access    public
     *
     * @param    mixed    $observer    The observer object to add (must extend {@link XOAD_Observer}).
     *
     * @return    string    true on success, false otherwise.
     *
     * @static
     *
     */
    public static function addObserver(&$observer, $className = 'XOAD_Serializer')
    {
        return parent::addObserver($observer, $className);
    }

    /**
     *
     * @access    public
     *
     * @return    bool
     *
     */
    public static function notifyObservers($event = 'default', $arg = null, $className = 'XOAD_Serializer')
    {
        return parent::notifyObservers($event, $arg, $className);
    }
}


class XOAD_Client extends XOAD_Observable
{
    /**
     * Registers a PHP variable/class in JavaScript.
     *
     * <p>Example:</p>
     * <code>
     * <script type="text/javascript">
     * <?php require_once('xoad.php'); ?>
     *
     * var arr = <?= XOAD_Client::register(array(1, 2, "string", array("Nested"))) ?>;
     *
     * alert(arr);
     *
     * </script>
     * </code>
     *
     * @access    public
     *
     * @param    mixed    $var    Variable/Class name to register.
     *
     * @param    mixed    $params    When registering a variable/class you can
     *                            provide extended parameters, like class name
     *                            and callback URL.
     *
     * @return    string    JavaString code that represents the variable/class.
     *
     * @static
     *
     */
    public static function register($var, $params = null)
    {
        $type = XOAD_Utilities::getType($var);

        if ($type == 'object') {

            $paramsType = XOAD_Utilities::getType($params);

            if ($paramsType != 'string') {

                $callbackUrl = XOAD_Utilities::getRequestUrl();

                if ($paramsType == 'a_array') {

                    if ( ! empty($params['class'])) {

                        $className = $params['class'];
                    }

                    if ( ! empty($params['url'])) {

                        $callbackUrl = $params['url'];
                    }
                }

            } else {

                $callbackUrl = $params;
            }

            if (method_exists($var, XOAD_CLIENT_METADATA_METHOD_NAME)) {

                call_user_func_array(array(&$var, XOAD_CLIENT_METADATA_METHOD_NAME), array());
            }

            $objectCode = array();

            if (empty($className)) {

                $className = XOAD_Utilities::caseConvert(get_class($var));
            }

            $meta = get_object_vars($var);

            $objectMeta = null;

            if (isset($meta['xoadMeta'])) {

                if (XOAD_Utilities::getType($meta['xoadMeta']) == 'object') {

                    if (strcasecmp(get_class($meta['xoadMeta']), 'XOAD_Meta') == 0) {

                        $objectMeta = $meta['xoadMeta'];

                        unset($meta['xoadMeta']);

                        unset($var->xoadMeta);
                    }
                }
            }

            if (sizeof($meta) > 0) {

                $attachMeta = array();

                foreach ($meta as $key => $value) {

                    if ( ! empty($objectMeta)) {

                        if ( ! $objectMeta->isPublicVariable($key)) {

                            unset($meta[$key]);

                            unset($var->$key);

                            continue;
                        }
                    }

                    $valueType = XOAD_Utilities::getType($value);

                    if (
                    ($valueType == 'object') ||
                    ($valueType == 's_array') ||
                    ($valueType == 'a_array')) {

                        $var->$key = XOAD_SERIALIZER_SKIP_STRING . XOAD_Client::register($var->$key, $callbackUrl);
                    }

                    $attachMeta[$key] = $valueType;
                }

                $var->__meta = $attachMeta;

                $var->__size = sizeof($attachMeta);

            } else {

                $var->__meta = null;

                $var->__size = 0;
            }

            $var->__class = $className;

            $var->__url = $callbackUrl;

            $GLOBALS['__uid'] =$var->__uid = md5(uniqid(rand(), true));

            $var->__output = null;

            $var->__timeout = null;

            $serialized = XOAD_Serializer::serialize($var);

            $objectCode[] = substr($serialized, 1, strlen($serialized) - 2);

            $classMethods = get_class_methods($var);

            for ($iterator = sizeof($classMethods) - 1; $iterator >= 0; $iterator --) {

                if (strcasecmp($className, $classMethods[$iterator]) == 0) {

                    unset($classMethods[$iterator]);

                    continue;
                }

                if (strcasecmp($classMethods[$iterator], XOAD_CLIENT_METADATA_METHOD_NAME) == 0) {

                    unset($classMethods[$iterator]);

                    continue;
                }

                if ( ! empty($objectMeta)) {

                    if ( ! $objectMeta->isPublicMethod($classMethods[$iterator])) {

                        unset($classMethods[$iterator]);

                        continue;
                    }
                }
            }

            if (sizeof($classMethods) > 0) {

                $index = 0;

                $length = sizeof($classMethods);

                $returnValue = '';

                foreach ($classMethods as $method) {

                    $methodName = XOAD_Utilities::caseConvert($method);

                    if ( ! empty($objectMeta)) {

                        $mapMethodName = $objectMeta->findMethodName($methodName);

                        if (strcmp($mapMethodName, $methodName) != 0) {

                            $methodName = $mapMethodName;
                        }
                    }

                    $serialized = XOAD_Serializer::serialize($methodName);

                    $returnValue .= $serialized;

                    $returnValue .= ':';

                    $returnValue .= 'function(){return xoad.call(this,' . $serialized .',arguments)}';

                    if ($index < $length - 1) {

                        $returnValue .= ',';
                    }

                    $index ++;
                }

                $objectCode[] = $returnValue;
            }

            $returnValue = '{' . join(',', $objectCode) . '}';

            return $returnValue;

        } else if (($type == 's_array') || ($type == 'a_array')) {

            foreach ($var as $key => $value) {

                $valueType = XOAD_Utilities::getType($value);

                if (
                ($valueType == 'object') ||
                ($valueType == 's_array') ||
                ($valueType == 'a_array')) {

                    $var[$key] = XOAD_SERIALIZER_SKIP_STRING . XOAD_Client::register($var[$key], $params);
                }
            }

        } else if ($type == 'string') {

            $paramsType = XOAD_Utilities::getType($params);

            if ($paramsType == 'string') {

                if (class_exists($var)) {

                    $classObject = new $var;

                    $classCode = XOAD_Client::register($classObject, array('class' => $var, 'url' => $params));

                    $classCode = $var . '=function(){return ' . $classCode . '}';

                    return $classCode;
                }
            }
        }

        return XOAD_Serializer::serialize($var);
    }

    /**
     * Assigns public methods to the class meta data.
     *
     * @param    object    $var        The object where the meta data is stored.
     *
     * @param    array    $methods    The class public methods.
     *
     * @return    void
     *
     * @static
     *
     */
    public static function publicMethods(&$var, $methods)
    {
        if (XOAD_Utilities::getType($var) != 'object') {

            return false;
        }

        if ( ! isset($var->xoadMeta)) {

            require_once(XOAD_BASE . '/classes/Meta.class.php');

            $var->xoadMeta = new XOAD_Meta();
        }

        $var->xoadMeta->setPublicMethods($methods);
        
        return true;
    }

    /**
     * Assigns private methods to the class meta data.
     *
     * @param    object    $var        The object where the meta data is stored.
     *
     * @param    array    $methods    The class private methods.
     *
     * @return    void
     *
     * @static
     *
     */
    public static function privateMethods(&$var, $methods)
    {
        if (XOAD_Utilities::getType($var) != 'object') {

            return false;
        }

        if ( ! isset($var->xoadMeta)) {

            require_once(XOAD_BASE . '/classes/Meta.class.php');

            $var->xoadMeta = new XOAD_Meta();
        }

        $var->xoadMeta->setPrivateMethods($methods);
        
        return true;
    }

    /**
     * Assigns public variables to the class meta data.
     *
     * @param    object    $var        The object where the meta data is stored.
     *
     * @param    array    $variables    The class public variables.
     *
     * @return    void
     *
     * @static
     *
     */
    public static function publicVariables(&$var, $variables)
    {
        if (XOAD_Utilities::getType($var) != 'object') {

            return false;
        }

        if ( ! isset($var->xoadMeta)) {

            require_once(XOAD_BASE . '/classes/Meta.class.php');

            $var->xoadMeta = new XOAD_Meta();
        }

        $var->xoadMeta->setPublicVariables($variables);
        
        return true;
    }

    /**
     * Assigns private variables to the class meta data.
     *
     * @param    object    $var        The object where the meta data is stored.
     *
     * @param    array    $variables    The class private variables.
     *
     * @return    void
     *
     * @static
     *
     */
    public static function privateVariables(&$var, $variables)
    {
        if (XOAD_Utilities::getType($var) != 'object') {

            return false;
        }

        if ( ! isset($var->xoadMeta)) {

            require_once(XOAD_BASE . '/classes/Meta.class.php');

            $var->xoadMeta = new XOAD_Meta();
        }

        $var->xoadMeta->setPrivateVariables($variables);
        
        return true;
    }

    /**
     * Assigns methods map to the class meta data.
     *
     * @param    object    $var        The object where the meta data is stored.
     *
     * @param    array    $methodsMap    The class methods map.
     *
     * @return    void
     *
     * @static
     *
     */
    public static function mapMethods(&$var, $methodsMap)
    {
        if (XOAD_Utilities::getType($var) != 'object') {

            return false;
        }

        if ( ! isset($var->xoadMeta)) {

            require_once(XOAD_BASE . '/classes/Meta.class.php');

            $var->xoadMeta = new XOAD_Meta();
        }

        $var->xoadMeta->setMethodsMap($methodsMap);
        
        return true;
    }

    /**
     * Adds a {@link XOAD_Client} events observer.
     *
     * @access    public
     *
     * @param    mixed    $observer    The observer object to add (must extend {@link XOAD_Observer}).
     *
     * @return    string    true on success, false otherwise.
     *
     * @static
     *
     */
    public static function addObserver(&$observer, $className = 'XOAD_Client')
    {
        return parent::addObserver($observer, $className);
    }

    /**
     *
     * @access    public
     *
     * @return    bool
     *
     */
    public static function notifyObservers($event = 'default', $arg = null, $className = 'XOAD_Client')
    {
        return parent::notifyObservers($event, $arg, $className);
    }
}

 

class XOAD_Server extends XOAD_Observable
{
    /**
     * Checks if the request is a client callback
     * to the server and handles it.
     *
     * @access    public
     *
     * @return    bool    true if the request is a valid client callback,
     *                    false otherwise.
     *
     * @static
     *
     */
    public static function runServer()
    {
        if ( ! XOAD_Server::notifyObservers('runServerEnter')) {

            return false;
        }

        if (XOAD_Server::initializeCallback()) {

            XOAD_Server::dispatch();

            /**
             * Defines whether the request is a client callback.
             */
            define('XOAD_CALLBACK', true);
        }

        if ( ! defined('XOAD_CALLBACK')) {

            /**
             * Defines whether the request is a client callback.
             */
            define('XOAD_CALLBACK', false);
        }

        if (XOAD_Server::notifyObservers('runServerLeave', array('isCallback' => XOAD_CALLBACK))) {

            return XOAD_CALLBACK;

        } else {

            return false;
        }
    }

    /**
     * Checks if the request is a client callback to the
     * server and initializes callback parameters.
     *
     * @access    public
     *
     * @return    bool    true if the request is a valid client callback,
     *                    false otherwise.
     *
     * @static
     *
     */
    public static function initializeCallback()
    {
        if ( ! XOAD_Server::notifyObservers('initializeCallbackEnter')) {

            return false;
        }

        if (isset($_GET['xoadCall'])) {

            if (strcasecmp($_GET['xoadCall'], 'true') == 0) {

                $ROW_POST=file_get_contents('php://input');
                
                if ( ! isset($ROW_POST)) {

                    return false;
                }

                $requestBody = @unserialize($ROW_POST);

                if ($requestBody == null) {

                    return false;
                }

                if (
                isset($requestBody['eventPost']) &&
                isset($requestBody['className']) &&
                isset($requestBody['sender']) &&
                isset($requestBody['event']) &&
                array_key_exists('data', $requestBody) &&
                array_key_exists('filter', $requestBody)) {

                    if (
                    (XOAD_Utilities::getType($requestBody['eventPost']) != 'bool') ||
                    (XOAD_Utilities::getType($requestBody['className']) != 'string') ||
                    (XOAD_Utilities::getType($requestBody['sender']) != 'string') ||
                    (XOAD_Utilities::getType($requestBody['event']) != 'string')) {

                        return false;
                    }

                    if ( ! empty($requestBody['className'])) {

                        XOAD_Server::loadClass($requestBody['className']);

                    } else {

                        return false;
                    }

                    if ( ! XOAD_Server::isClassAllowed($requestBody['className'])) {

                        return false;
                    }

                    $requestBody['sender'] = @unserialize($requestBody['sender']);

                    if ($requestBody['sender'] === null) {

                        return false;
                    }

                    if (strcasecmp(get_class($requestBody['sender']), $requestBody['className']) != 0) {

                        return false;
                    }

                    if ( ! XOAD_Server::notifyObservers('initializeCallbackSuccess', array('request' => &$requestBody))) {

                        return false;
                    }

                    $GLOBALS['_XOAD_SERVER_REQUEST_BODY'] =& $requestBody;

                    if (XOAD_Server::notifyObservers('initializeCallbackLeave', array('request' => &$requestBody))) {

                        return true;
                    }

                } else if (
                isset($requestBody['eventsCallback']) &&
                isset($requestBody['time']) &&
                isset($requestBody['data'])) {

                    if (
                    (XOAD_Utilities::getType($requestBody['eventsCallback']) != 'bool') ||
                    (XOAD_Utilities::getType($requestBody['time']) != 'float') ||
                    (XOAD_Utilities::getType($requestBody['data']) != 's_array')) {

                        return false;
                    }

                    foreach ($requestBody['data'] as $eventData) {

                        if ( ! empty($eventData['className'])) {

                            XOAD_Server::loadClass($eventData['className']);

                        } else {

                            return false;
                        }

                        if ( ! XOAD_Server::isClassAllowed($eventData['className'])) {

                            return false;
                        }
                    }

                    if ( ! XOAD_Server::notifyObservers('initializeCallbackSuccess', array('request' => &$requestBody))) {

                        return false;
                    }

                    $GLOBALS['_XOAD_SERVER_REQUEST_BODY'] =& $requestBody;

                    if (XOAD_Server::notifyObservers('initializeCallbackLeave', array('request' => &$requestBody))) {

                        return true;
                    }

                } else {

                    if (
                    ( ! isset($requestBody['source'])) ||
                    ( ! isset($requestBody['className'])) ||
                    ( ! isset($requestBody['method'])) ||
                    ( ! isset($requestBody['arguments']))) {

                        return false;
                    }

                    if ( ! empty($requestBody['className'])) {

                        XOAD_Server::loadClass($requestBody['className']);
                    }

                    $requestBody['source'] = @unserialize($requestBody['source']);

                    $requestBody['arguments'] = @unserialize($requestBody['arguments']);

                    if (
                    ($requestBody['source'] === null) ||
                    ($requestBody['className'] === null) ||
                    ($requestBody['arguments'] === null)) {

                        return false;
                    }

                    if (
                    (XOAD_Utilities::getType($requestBody['source']) != 'object') ||
                    (XOAD_Utilities::getType($requestBody['className']) != 'string') ||
                    (XOAD_Utilities::getType($requestBody['method']) != 'string') ||
                    (XOAD_Utilities::getType($requestBody['arguments']) != 's_array')) {

                        return false;
                    }

                    if (strcasecmp($requestBody['className'], get_class($requestBody['source'])) != 0) {

                        return false;
                    }

                    if ( ! XOAD_Server::isClassAllowed($requestBody['className'])) {

                        return false;
                    }

                    if (method_exists($requestBody['source'], XOAD_CLIENT_METADATA_METHOD_NAME)) {

                        call_user_func_array(array(&$requestBody['source'], XOAD_CLIENT_METADATA_METHOD_NAME), array());

                        if (isset($requestBody['source']->xoadMeta)) {

                            if (XOAD_Utilities::getType($requestBody['source']->xoadMeta) == 'object') {

                                if (strcasecmp(get_class($requestBody['source']->xoadMeta), 'XOAD_Meta') == 0) {

                                    if ( ! $requestBody['source']->xoadMeta->isPublicMethod($requestBody['method'])) {

                                        return false;
                                    }
                                }
                            }
                        }
                    }

                    if ( ! XOAD_Server::notifyObservers('initializeCallbackSuccess', array('request' => &$requestBody))) {

                        return false;
                    }

                    $GLOBALS['_XOAD_SERVER_REQUEST_BODY'] =& $requestBody;

                    if (XOAD_Server::notifyObservers('initializeCallbackLeave', array('request' => &$requestBody))) {

                        return true;
                    }
                }
            }
        }

        XOAD_Server::notifyObservers('initializeCallbackLeave');

        return false;
    }

    /**
     * Dispatches a client callback to the server.
     *
     * @access    public
     *
     * @return    string    Outputs JavaString code that contains the result
     *                    and the output of the callback.
     *
     * @static
     *
     */
    public static function dispatch()
    {
        if (empty($GLOBALS['_XOAD_SERVER_REQUEST_BODY'])) {

            return false;
        }

        $requestBody =& $GLOBALS['_XOAD_SERVER_REQUEST_BODY'];

        if ( ! XOAD_Server::notifyObservers('dispatchEnter', array('request' => &$requestBody))) {

            return false;
        }

        if (isset($requestBody['eventPost'])) {

            $callbackResponse = array();
            
            $storage =& XOAD_Events_Storage::getStorage();
            
            $callbackResponse['status'] = $storage->postEvent($requestBody['event'], $requestBody['className'], $requestBody['sender'], $requestBody['data'], $requestBody['filter']);

            if (XOAD_Server::notifyObservers('dispatchLeave', array('request' => &$requestBody, 'response' => &$callbackResponse))) {

                if ( ! empty($callbackResponse['status'])) {

                    print XOAD_Client::register($callbackResponse);
                }
            }

        } else if (isset($requestBody['eventsCallback'])) {

            $eventsQuery = array();

            foreach ($requestBody['data'] as $event) {

                $eventsQuery[] = array(
                'event'        =>    $event['event'],
                'className'    =>    $event['className'],
                'filter'    =>    $event['filter'],
                'time'        =>    $requestBody['time']
                );
            }

            $callbackResponse = array();

            $storage =& XOAD_Events_Storage::getStorage();

            $storage->cleanEvents();

            $callbackResponse['result'] = $storage->filterMultipleEvents($eventsQuery);

            if (XOAD_Server::notifyObservers('dispatchLeave', array('request' => &$requestBody, 'response' => &$callbackResponse))) {

                if ( ! empty($callbackResponse['result'])) {

                    print XOAD_Client::register($callbackResponse);
                }
            }

        } else {

            $callbackResponse = array();

            $outputBuffering = @ob_start();

            set_error_handler(array('XOAD_Server', 'handleError'));

            $callbackResponse['returnValue'] = call_user_func_array(array(&$requestBody['source'], $requestBody['method']), $requestBody['arguments']);

            if (defined('XOAD_SERVER_EXCEPTION')) {

                if (XOAD_Server::notifyObservers('dispatchFailed', array('request' => &$requestBody, 'message' => XOAD_SERVER_EXCEPTION))) {

                    XOAD_Server::throwException(XOAD_SERVER_EXCEPTION);

                    return false;
                }
            }

            $callbackResponse['returnObject'] =& $requestBody['source'];

            if ($outputBuffering) {

                $output = @ob_get_contents();

                if ( ! empty($output)) {

                    $callbackResponse['output'] = $output;
                }

                @ob_end_clean();
            }

            restore_error_handler();

            if (XOAD_Server::notifyObservers('dispatchLeave', array('request' => &$requestBody, 'response' => &$callbackResponse))) {

                print XOAD_Client::register($callbackResponse);
            }
        }
        
        return true;
    }

    public static function handleError($type, $message)
    {
        if (error_reporting()) {

            if ( ! XOAD_Server::notifyObservers('handleErrorEnter', array('type' => &$type, 'message' => &$message))) {

                return false;
            }

            $breakLevel = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;

            if (($type & $breakLevel) > 0) {

                if ( ! defined('XOAD_SERVER_EXCEPTION')) {

                    /**
                     * Defines the error message that caused the callback to halt.
                     */
                    define('XOAD_SERVER_EXCEPTION', $message);
                }
            }
        }

        XOAD_Server::notifyObservers('handleErrorLeave', array('type' => &$type, 'message' => &$message));
        
        return true;
    }

    public static function throwException($message)
    {
        if ( ! XOAD_Server::notifyObservers('throwExceptionEnter', array('message' => &$message))) {

            return false;
        }

        restore_error_handler();

        $callbackException = array();

        $callbackException['exception'] = $message;

        if (XOAD_Server::notifyObservers('throwExceptionLeave', array('message' => &$message))) {

            print XOAD_Client::register($callbackException);
        }
        
        return true;
    }


    public static function mapClass($className, $files)
    {
        if ( ! isset($GLOBALS['_XOAD_SERVER_CLASSES_MAP'])) {

            $GLOBALS['_XOAD_SERVER_CLASSES_MAP'] = array();
        }

        $GLOBALS['_XOAD_SERVER_CLASSES_MAP'][strtolower($className)] = $files;
    }

    public static function loadClass($className)
    {
        $className = strtolower($className);

        if ( ! empty($GLOBALS['_XOAD_SERVER_CLASSES_MAP'])) {

            if (isset($GLOBALS['_XOAD_SERVER_CLASSES_MAP'][$className])) {

                $files = $GLOBALS['_XOAD_SERVER_CLASSES_MAP'][$className];

                $filesType = XOAD_Utilities::getType($files);

                if ($filesType == 'string') {

                    require_once($files);

                } else if (
                ($filesType == 's_array') ||
                ($filesType == 'a_array')) {

                    foreach ($files as $fileName) {

                        require_once($fileName);
                    }
                }
            }
        }
    }


    public static function allowClasses($classes)
    {
        $classesType = XOAD_Utilities::getType($classes);

        if ( ! isset($GLOBALS['_XOAD_SERVER_ALLOWED_CLASSES'])) {

            $GLOBALS['_XOAD_SERVER_ALLOWED_CLASSES'] = array();
        }

        $allowedClasses = $GLOBALS['_XOAD_SERVER_ALLOWED_CLASSES'];

        if ($classesType == 'string') {

            $allowedClasses[] = strtolower($classes);

        } else if (($classesType == 's_array') || ($classesType == 'a_array')) {

            foreach ($classes as $class) {

                $allowedClasses[] = strtolower($class);
            }
        }
    }

    public static function isClassAllowed($class)
    {
        $allowedClasses = null;

        $deniedClasses = null;

        if (isset($GLOBALS['_XOAD_SERVER_ALLOWED_CLASSES'])) {

            $allowedClasses =& $GLOBALS['_XOAD_SERVER_ALLOWED_CLASSES'];
        }

        if (isset($GLOBALS['_XOAD_SERVER_DENIED_CLASSES'])) {

            $deniedClasses =& $GLOBALS['_XOAD_SERVER_DENIED_CLASSES'];
        }

        if ( ! empty($deniedClasses)) {

            if (in_array(strtolower($class), $deniedClasses)) {

                return false;
            }
        }

        if ( ! empty($allowedClasses)) {

            if ( ! in_array(strtolower($class), $allowedClasses)) {

                return false;
            }
        }

        return true;
    }

    /**
     * Adds a {@link XOAD_Server} events observer.
     *
     * @access    public
     *
     * @param    mixed    $observer    The observer object to add (must extend {@link XOAD_Observer}).
     *
     * @return    string    true on success, false otherwise.
     *
     * @static
     *
     */
    public static function addObserver(&$observer, $className = 'XOAD_Server')
    {
        return parent::addObserver($observer, $className);
    }

    /**
     *
     * @access    public
     *
     * @return    bool
     *
     */
    public static function notifyObservers($event = 'default', $arg = null, $className = 'XOAD_Server')
    {
        return parent::notifyObservers($event, $arg, $className);
    }
}

if (!empty($xoadExtensions))
    {
    foreach ($xoadExtensions as $extension)
        {
        define('XOAD_' . strtoupper($extension) . '_BASE', XOAD_BASE . '/extensions/' . $extension);

        require_once(XOAD_BASE . '/extensions/' . $extension . '/' . $extension . '.ext.php');
        }
    }
?>