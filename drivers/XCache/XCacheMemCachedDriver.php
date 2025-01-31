<?php
class XCacheMemCachedDriver  implements X4\Classes\XCacheDriver
{
     public static $memcached;
     
     public static function initDriver($doNotDieAfterConnect=false)
    {
        if(class_exists('Memcached'))
        {
                if(!self::$memcached)
                {
                    self::$memcached = new Memcached;
                    
                    if(self::$memcached->connect(xConfig::get('GLOBAL','memcachedHost'), xConfig::get('GLOBAL','memcachedPort')))
                    {                        
                        return true;
                        
                    }elseif(!$doNotDieAfterConnect)
                    {
                       die ("memcached:Could not connect to server"); 
                       
                    }else
                    {
                       return false; 
                    }  
                }
        }
    }

    public static function  setMulti($data, $timeout = null)
    {
            self::$memcached->setMulti($data,time()+$timeout);      
        
    }
    
    
     public static function  getMulti($data)
    {
            self::$memcached->getMulti($data);      
        
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
            
             return self::$memcached->set($module.md5($id), $data,0,time()+$timeout);
    }
    
    public static  function read($module, $id, $timeout = null)
    {        
            return self::$memcached->get($module.md5($id));
    }
    
    public static function clearBranch($modules)
    {
       self::$memcached->flush();
    }
    
    
    public static  function clear($module, $id)
    {                                  
      self::$memcached->flush();
    }
   
}
?>
