<?php
class XCacheRedisDriver implements X4\Classes\XCacheDriver
{
     public static $Redis;
     
     public static function initDriver($doNotDieAfterConnect=false)
    {
        if(class_exists('Redis'))
        {
                if(!self::$Redis)
                {
                    self::$Redis = new Redis();
                    
                    if(self::$Redis->connect(xConfig::get('GLOBAL','RedisHost'), xConfig::get('GLOBAL','RedisPort')))                    
					{                        
						self::$Redis->select(xConfig::get('GLOBAL','RedisDBIndex'));
						
                        return true;
                        
                    }elseif(!$doNotDieAfterConnect)
                    {
                       die ("Redis:Could not connect to server"); 
                       
                    }else
                    {
                       return false; 
                    }  
                }
        }
    }

    public static function  setMulti($data,$module,$timeout = null)
    {
        if(!empty($data))
        {            
            
            foreach($data as $key=>$item)
            {
                $newItems[$module.$key]=serialize($item);            
            }
            
            self::$Redis->mSet($newItems);      
        }
        
    }
    
    
     public static function  getMulti($dataKeys,$module='')
    {
        
            if($module)
            {
                foreach($dataKeys as &$dta)
                {
                       $dta=$module.$dta;                    
                }    
                
            }
            
            $data=self::$Redis->mget($dataKeys);      
            
            if(!empty($data))
            {                
                foreach($data as &$item)
                {
                    $item=unserialize($item);
                }    
            
            return $data;
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
            
             return self::$Redis->set($module.$id, $data,0,time()+$timeout);
    }
    
    public static  function read($module, $id, $timeout = null)
    {        
            
            $d=self::$Redis->get($module.$id);
            
            if(!empty($d))
            {
                return array($id=>unserialize($d));
            }
    }
    
    
    public static function clearBranch($modules=null)
    {
       self::$Redis->flushAll();
    }
    
    
    public static  function clear($module=null, $id=null)
    {                                  
      self::$Redis->flushAll();
    }
   
}

