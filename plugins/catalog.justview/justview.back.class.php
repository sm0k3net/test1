<?php
class justviewBack  extends xPluginBack
{
    public function __construct($name,$module)
    {     
        parent::__construct($name,$module);
        $this->_listener->defineFrontActions($this->_module->_commonObj);              
    }  
    
    
    public function onAction_justViewed($params)    
    {
        if (isset($params['data']['params']))
        {
                $this->result['actionDataForm']=$params['data']['params'];                
                $dtc=$this->_module->_tree->selectStruct(array('id'))->getParamPath('Name')->where(array('@id','=',$params['data']['params']['showCategoryPointId']))->run();
                $this->_module->result['actionDataForm']['showCategoryPoint']=$dtc['paramPathValue'];
     
        }
        
    }

}

?>