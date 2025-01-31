<?php
class XCacheMemCacheDriver implements X4\Classes\XCacheDriver
{
     public static $memcache;
     
     public static function initDriver($doNotDieAfterConnect=false)
    {
        if(class_exists('Memcache'))
        {
                if(!self::$memcache)
                {
                    self::$memcache = new Memcache;
                    
                    if(self::$memcache->connect(xConfig::get('GLOBAL','memcacheHost'), xConfig::get('GLOBAL','memcachePort')))
                    {                        
                        return true;
                        
                    }elseif(!$doNotDieAfterConnect)
                    {
                       die ("memcache:Could not connect to server"); 
                       
                    }else
                    {
                       return false; 
                    }  
                }
                }
    }

    
    public static function  serializedRead($module, $id, $timeout = null)
    {
         if ($data = self::read($module, $id, $timeout))
        {
            return $data;
        }
        
    }
    public static  function serializedWrite($data, $module, $id, $timeout = null)
    {
        return   self::write($data, $module, $id, $timeout);
            
    }
    

    
    public  static function write($data, $module, $id, $timeout = null)
    {
        
            if ($timeout === null)
            {
                $timeout = xConfig::get('GLOBAL', 'cacheTimeout');
            }
            
             return self::$memcache->set($module.md5($id), $data,0,time()+$timeout);
    }
    
    public static  function read($module, $id, $timeout = null)
    {
        //         self::getKeys();
        
            return self::$memcache->get($module.md5($id));
    }
    
    public static function clearBranch($modules)
    {
       self::$memcache->flush();
    }
    
    public static function getKeys()
    {
        
            $allSlabs = self::$memcache->getExtendedStats('slabs');  
            $items =self::$memcache->getExtendedStats('items');  
              
            foreach ($allSlabs as $server => $slabs) {  
                foreach ($slabs as $slabId => $slabMeta) {  
                    if (!is_numeric($slabId)) {  
                        continue;  
                    }  
                  
                    $cdump = self::$memcache->getExtendedStats('cachedump', (int)$slabId, $limit);  
                      
                    foreach ($cdump as $server => $entries) {  
                        if (!$entries) {  
                            continue;  
                        }  
                          
                        foreach($entries as $eName => $eData) {  
                            $list[$eName] = array(  
                                'key' => $eName,  
                                'slabId' => $slabId,  
                                'size' => $eData[0],  
                                'age' => $eData[1]  
                            );  
                        }  
                    }  
                }  
            }  
              
            ksort($list);  
        
    }
    
    
    public static  function clear($module, $id)
    {                                  
      self::$memcache->flush();
    }
   
}
?>
