<?php

namespace X4\Classes;

abstract class MultiSectionObject
{
    public $objectCovered;
    public $coverName;
    private $coverNs;

    public function __construct($objectCovered, $coverName)
    {
        $this->objectCovered = $objectCovered;
        $this->coverName = $coverName;
        $this->coverNs = 'cover:' . $coverName;
    }

    public function registerMethod($methods, $object)
    {

        XNameSpaceHolder::addMethodsToNS($this->coverNs, $methods, $object);
    }

    public function getCoverObject()
    {
        return $this->objectCovered;
    }

    public function call($method, $params)
    {
        if (XNameSpaceHolder::isNameSpaceExists($this->coverNs)) {
            return XNameSpaceHolder::call($this->coverNs, $this, $params, array('instance' => $this, 'objectCovered' => $this->objectCovered));
        } elseif (method_exists($this, $method)) {
            return $this->{$method}($params);
        }
    }

}


