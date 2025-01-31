<?php
use X4\Classes\XNameSpaceHolder;
use X4\Classes\XRegistry;
use X4\Classes\MultiSection;
use X4\Classes\XCache;

class mfBack  extends xPluginBack
{
    public function __construct($name,$module)
    {     
        parent::__construct($name,$module);
        $this->_listener->defineFrontActions($this->_module->_commonObj);              
    }  
    
    
    

}
