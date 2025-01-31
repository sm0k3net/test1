<?php
/**
 * Родительский класс  для API составляющей модулей.
 */
class xModuleApi
    extends xModulePrototype
{
    public function __construct($className)
    {
        parent::__construct($className);
    }

    public function error($errorText, $errorCode)
    {
        return array(
            'result' => false,
            'error' => array(
                'text' => $errorText,
                'code' => $errorCode
            )
        );
    }
}
