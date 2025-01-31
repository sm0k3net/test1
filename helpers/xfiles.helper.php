<?php
class XFILES
{
    
    public static function getDirSize($directory) 
    {
            $size = 0;
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
                $size += $file->getSize();
            }
            return $size;
    }


    public static function isWritable($path)
    {
        if ($path{strlen($path) - 1} == '/')
            return self::isWritable($path . uniqid(mt_rand()) . '.tmp');
        if (file_exists($path))
        {
            if (!($f = @fopen($path, 'r+')))
                return false;
            fclose($f);
            return true;
        }
        
        if (!($f = @fopen($path, 'w')))
            return false;
        fclose($f);
        unlink($path);
        return true;
    }
    
    public static function fileWrite($filename, $data)
    {
        if (!$handle = fopen($filename, 'w'))
        {
            exit;
        }
        
        if (fwrite($handle, $data) === FALSE)
        {
            exit;
        }
        
        fclose($handle);
        return true;
    }
    
    public static function directoryList($pth, $types = 'directories', $recursive = 0, $full = false)
    {

        $pt = $pth;

        if ($dir = opendir($pth))
        {
            $fileList = array();
            while (false !== $file = readdir($dir))
            {
                if (($file != '.' AND $file != '..'))
                {
                    if ((is_dir($pth . '/' . $file) AND ($types == 'directories' OR $types == 'all')))
                    {
                        if (!$full)
                        {
                            $p           = str_replace($pt, '', $pth . $file);
                            $fileList[] = $p;
                        }
                        else
                        {
                            $fileList[] = $pth . $file;
                        }
                        
                        if ($recursive)
                        {
                            $fileList = array_merge($fileList, XFILES::directoryList($pth . '/' . $file . '/', $types, $recursive));
                            continue;
                        }
                        
                        continue;
                    }
                    
                    if (($types == 'files' OR $types == 'all'))
                    {
                        if (!$full)
                        {
                            $p           = str_replace($pt, '', $pth . $file);
                            $fileList[] = $p;
                        }
                        else
                        {
                            $fileList[] = $pth . $file;
                        }
                        
                        continue;
                    }
                    
                    continue;
                }
            }
            
            closedir($dir);
            return $fileList;
        }
        else
        {
            return FALSE;
        }
    }
    
    
   public static function downloadFile($file) 
   { 
        if(file_exists($file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename='.basename($file));
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            ob_clean();
            flush();
            readfile($file);
            exit;
        }

    }
    
    
    public static function formatSize($size, $round = 2)
    {
        $sizes = array(
            'B',
            'kB',
            'MB',
            'GB',
            'TB',
            'PB',
            'EB',
            'ZB',
            'YB'
        );
        for ($i = 0; $size > 1024 && isset($sizes[$i + 1]); $i++)
            $size /= 1024;
        return round($size, $round) . $sizes[$i];
    }
    
    public static function unlinkRecursive($dir, $deleteRootToo=false,$preventDirDelete=false,$excludefolders=null)
    {
        
        if (!$dh = @opendir($dir))
        {
            return;
        }
        
        while (false !== ($obj = readdir($dh)))
        {
            if ($obj == '.' || $obj == '..')
            {
                continue;
            }
            
            if($excludefolders)
            {
                
              if(in_array($obj,$excludefolders))continue;
            }
            
            if (!@unlink($dir . '/' . $obj))
            {
                XFILES::unlinkRecursive($dir . '/' . $obj, true,$preventDirDelete);
            }
        }
        
        closedir($dh);
        if ($deleteRootToo&&(!$preventDirDelete))
        {
            @rmdir($dir);
        }
        
        return;
    }
    
    public static function filesList($pth, $types = 'files', $allow_types = null, $recursive = 0, $get_basenames = false)
    {
        if ($dir = @opendir($pth))
        {
            $fileList = array();
            while (FALSE !== $file = readdir($dir))
            {
                if (($file != '.' AND $file != '..'))
                {
                    if ((is_dir($pth . '/' . $file) AND ($types == 'directories' OR $types == 'all')))
                    {
                        $fileList[] = $file;
                        if ($recursive)
                        {
                            $fileList = array_merge($fileList, XFILES::directoryList($pth . '/' . $file . '/', $types, $recursive, !$get_basenames));
                            continue;
                        }
                        
                        continue;
                    }
                    
                    if (($types == 'files' AND !is_dir($pth . '/' . $file)))
                    {
                        if (is_array($allow_types))
                        {
                            preg_match("/\.(.?)+/", $file, $ftype);
                            if (in_array($ftype[0], $allow_types) != false)
                            {
                                if (!$get_basenames)
                                {
                                    $fileList[] = $pth . '/' . $file;
                                }
                                else
                                {
                                    $fileList[] = $file;
                                }
                            }
                        }
                        else
                        {
                            if (!$get_basenames)
                            {
                                $fileList[] = $pth . '/' . $file;
                            }
                            else
                            {
                                $fileList[] = $file;
                            }
                        }
                        
                        continue;
                    }
                    else
                    {
                        if ($types == 'all')
                        {
                            if (is_array($allow_types))
                            {
                                preg_match("/\.(.?)+/", $file, $ftype);
                                if (in_array(strtolower($ftype[0]), $allow_types) != false)
                                {
                                    if (!$get_basenames)
                                    {
                                        $fileList[] = $pth . '/' . $file;
                                    }
                                    else
                                    {
                                        $fileList[] = $file;
                                    }
                                }
                            }
                            else
                            {
                                if (!$get_basenames)
                                {
                                    $fileList[] = $pth . '/' . $file;
                                }
                                else
                                {
                                    $fileList[] = $file;
                                }
                            }
                            
                            continue;
                        }
                        
                        continue;
                    }
                    
                    continue;
                }
            }
            
            closedir($dir);
            return $fileList;
        }
        
        return FALSE;
    }
}

?>
