<?php
         
use X4\Classes\XTreeEngine;
use X4\Classes\XRegistry;
use X4\Classes\MultiSection;
use X4\Classes\XPDO;                                     

class justviewListener extends xListener  implements xPluginListener
{
    public function __construct()
    {
        
        parent::__construct('catalog.justview');        
        $this->_EVM->on('catalog.showObject:objectReady','registerViewed',$this);
        $this->_EVM->on('catalog.front:afterInit','afterModuleInit',$this);          
        $this->useModuleTplNamespace();
    }  
    
  
    public function registerViewed($params)
    {

          if(isset($params['data']['object']['_main']['id']))
          {
                $_SESSION['user']['justViewed'][$params['data']['object']['_main']['id']]= $params['data']['object'];
          }
    
    }
    
    
     public function afterModuleInit($moduleInstance)
      {                             
        $this->defineFrontActions($moduleInstance['data']['instance']->_commonObj);   
      }
      
      
                                
      public function defineFrontActions(xCommonInterface $moduleInstance)
        {                
            $moduleInstance->defineAction('justViewed',array('callContext'=>$this,'priority'=>8));
        }
    
}

?>