<?php
                
use X4\Classes\XCache;                  

class XCacheFileDriver implements X4\Classes\XCacheDriver
{
    public static function initDriver()
    {
        
    }
    
    public static function  serializedRead($module, $id, $timeout = null)
    {
        if ($data = XCacheFileDriver::read($module, $id, $timeout))
        {
            return unserialize($data);
        }else{
            
            return false;
        }
    }
    public static  function serializedWrite($data, $module, $id, $timeout = null)
    {
       return XCacheFileDriver::write(serialize($data), $module, $id, $timeout);
    }
    
	public static function createDir($module){
	
		if (!is_dir(xConfig::get('PATH', 'CACHE')  . $module))
        {
            if(!mkdir($folder=xConfig::get('PATH', 'CACHE')  . $module, xConfig::get('GLOBAL', 'defaultModeFiles'),true))
            {
                throw new Exception('Folder did not written - permission not set: '.$folder);     
				
            }else{                
			
                chmod($folder, 0777);                
            }
        }
		
	
	}
	
    public static function writeFile($data, $module, $fileName = '')
    {
    
        if (!$fileName)
        {
            $fileName = Common::generateHash();
        }
        
		self::createDir($module);
                 
        XCache::cacheWriteSize(strlen($data));
        
        if (file_put_contents($file=xConfig::get('PATH', 'CACHE')  . $module . '/' . $fileName, $data))
        {
				return $fileName;
            
        }else{
                throw new Exception('File did not written - permission not set: '.$file);                
        }
    }
    
    public  static function write($data, $module, $id, $timeout = null)
    {
    
        if ($timeout === null)
        {
            $timeout = xConfig::get('GLOBAL', 'cacheTimeout');
        }
        $fileExists = file_exists($file = xConfig::get('PATH', 'CACHE')  . $module . '/' . md5($module . $id));
     
        if ($timeout === false)
        {
            $timer = true;
        }
        elseif ($fileExists)
        {
            $timer = (filemtime($file) + $timeout) > time();
        }
        else
        {
            $timer = $timeout;
        }
       
        if ((!$fileExists) || ($timer))
        {
            XCacheFileDriver::writeFile($data, $module, md5($module . $id));
        }
    }
    
    public static  function read($module, $id, $timeout = null)
    {
        clearstatcache();
        
        if ($timeout === null)
        {
            $timeout = xConfig::get('GLOBAL', 'cacheTimeout');
        }
                
        
        $fileExists = file_exists($file = xConfig::get('PATH', 'CACHE') . $module . '/' . md5($module . $id));
        
        $timer=false;
        
        if ($timeout === false)
        {
            $timer = true;
        }
        elseif ($fileExists)
        {
            $timer = (filemtime($file) + $timeout) > time();
        }
        if (($file) && ($timer))
        {
            return XCacheFileDriver::readFile($module, md5($module . $id));
        
        }elseif(!$timer&&$fileExists)
        {
            XCacheFileDriver::clear($module, $id);    
            
            return false;
        }
    }
    
    public static function clearBranch($modules)
    {
        
        if (is_array($modules))
        {
            foreach ($modules as $dir)
            {
                XFILES::unlinkRecursive(xConfig::get('PATH', 'CACHE') . $dir, 0);
            }
        }
    }
    
    public static  function clear($module, $id)
    {                                  
        
//        if(file_exists($file=xConfig::get('PATH', 'CACHE') . '/' . $module . '/' . md5($module . $id)))
        $file=xConfig::get('PATH', 'CACHE') . '/' . $module . '/' . md5($module . $id);
        unlink($file);
    }
    
    
    public  static function fileExists($module, $file)
    {
            if(file_exists($file=xConfig::get('PATH', 'CACHE') . '/' . $module . '/' . $file))
                {
                        return true;
                }
    }
    
    public  static function readFile($module, $file)
    {
        static $gstatic;
        
        
        if(file_exists($file=xConfig::get('PATH', 'CACHE') . '/' . $module . '/' . $file))
        {
            
            if ($res = file_get_contents($file))
            {
                XCache::cacheLog($file);
                XCache::cacheReadSize(filesize($file));
                
                return $res;
            }
        }
    }
}
?>