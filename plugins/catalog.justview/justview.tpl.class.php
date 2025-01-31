<?php
class justviewTpl extends xTpl implements xPluginTpl
{
    
    public function __construct() {
  
    }
    
    
    public function countViewed($params)
    {        
        return  count($_SESSION['user']['justViewed']);
    }
    
   public function getJustViewed($params)
    {
      
        if($params['count'])
        {
             $resArr=array_slice($_SESSION['user']['justViewed'],-1*$params['count'],$params['count']);
            
        }else{
    
             $resArr= $_SESSION['user']['justViewed'];
        }
        
        if(isset($resArr))return array_reverse($resArr);
        
    }
    
}

?>