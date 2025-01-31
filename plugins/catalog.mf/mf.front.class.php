<?php

use X4\Classes\XNameSpaceHolder;
use X4\Classes\XRegistry;
use X4\Classes\MultiSection;
use X4\Classes\XCache;


class mfFront extends xPlugin
{
    
    private $listenerInstance;
    
    public function __construct($listenerInstance=null)
    {      
        $this->listenerInstance=$listenerInstance;                 
        parent::__construct(__FILE__); 
    }
    
}
