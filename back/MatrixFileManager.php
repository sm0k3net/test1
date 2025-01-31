<?php

namespace X4\AdminBack;

class MatrixFileManager
{
    public $options = array();
    public $result;

    /*  JAVASCRIPT

        !$this->options['extensionsMatrix'] =$eMatrix['gif']=array['icon']=/path/to/image.gif;
        !$this->options['transformImageSourceWidth']         =100px;
        * 
        */

    /*  $this->options['walkpath']         =$path;
     $this->options['allowFilesTypes']=$allow_files_types_array;
     $this->options['captureType']='all';'files';dirs*/

    public function __construct()
    {
        $this->options['captureType'] = 'all';
        $this->options['path'] = \xConfig::get('PATH', 'MEDIA');
        $this->options['mode'] = 'icons';

        $this->options['imageExtensions'] = array
        (
            '.jpg',
            '.gif',
            '.png',
            '.bmp',
            '.jpeg',
            '.swf'
        );

        $this->options['allowFilesTypes'] = \xConfig::get('GLOBAL', 'allowedFileUploadExt');
    }

    public function setOptions($options)
    {
        if ($options) {
            array_merge($this->options, $options);
        }
    }

    public function createFolder($params)
    {
        mkdir($f = $this->path($params['name']), \xConfig::get('GLOBAL', 'defaultModeFiles'));
        $this->result['folderCreated'] = chmod($f, \xConfig::get('GLOBAL', 'defaultModeFiles'));
    }

    public function path($name)
    {
        return $this->options['path'] . $_SESSION['xmatrix']['currentPath'] . '/' . $name;
    }

    public function unlinkFiles($params)
    {

        if (is_array($params['names'])) {
            foreach ($params['names'] as $f) {
                if (is_dir($npath = $this->path($f))) {
                    $this->unlinkRecursive($npath, true);
                } else {
                    unlink($npath);
                }
            }

            $this->result['unlink'] = true;
        } else {
            $this->result['unlink'] = false;
        }
    }

    public function unlinkRecursive($dir, $deleteRootToo)
    {
        return \XFILES::unlinkRecursive($dir, $deleteRootToo);
    }

    public function getWalk($params)
    {

        if ($params['path'] == "null") {
            $params['path'] = '';
        }

        $this->options['mode'] = $params['mode'];
        $this->options['filter'] = $params['filter'];
        $this->result['filesMatrix'] = $this->_walk($this->options['path'] . $params['path']);
        $_SESSION['xmatrix']['currentPath'] = $this->result['currentPath'] = $params['path'];
    }

    public function _walk($path)
    {
        if ($this->options['filter'] != '*') {
            $ftypes = $this->options['filter'];
        } elseif ($this->options['mode'] == 'images') {
            $ftypes = $this->options['imageExtensions'];
        } elseif ($this->options['mode'] == 'folders') {
            $this->options['captureType'] = 'directories';
            $ftypes = null;
        } else {
            $ftypes = null;
        }

        if ($files = \XFILES::filesList($path, $this->options['captureType'], $ftypes, 0, 1)) {
            natcasesort($files);

            $filematrix = array();

            $dirs = array();

            foreach ($files as $file) {
                if (is_dir($npath = $path . '/' . $file)) {
                    $dir = true;
                } else {
                    $dir = false;
                }

                $pathinfo = pathinfo($npath);
                $mod = filemtime($npath);
                $file = iconv('windows-1251', 'UTF-8', $file);
                $cfile = array('nam' => $file, 'mod' => date('d.m.Y H:i:s', $mod));

                $pathinfo['extension'] = strtolower($pathinfo['extension']);


                if ($pathinfo['extension'] == 'htaccess') {
                    continue;
                }

                if (!in_array($pathinfo['extension'], $this->options['allowFilesTypes'])) {
                    $pathinfo['extension'] = '_ukn';
                }

                if ($dir) {
                    $cfile['ext'] = 'dir';
                    array_push($dirs, $cfile);
                } else {
                    $cfile['ext'] = $pathinfo['extension'];

                    $size = \XFILES::formatSize(@filesize($npath));

                    $cfile['size'] = $size;

                    if (in_array($cfile['ext'], $this->options['imageExtensions'])) {
                        $size = getimagesize($npath);
                        $cfile['wh'] = $size[0] . 'x' . $size[1];
                    }

                    array_push($filematrix, $cfile);
                }
            }

            if ($dirs) {
                $filematrix = array_merge($dirs, $filematrix);
            }

            return $filematrix;
        }
    }

    public function pushFileToDownload($params)
    {
        $_SESSION['user']['lastFileToDownload'] = \xConfig::get('PATH', 'MEDIA') . $params['name'];
        $this->result['pushed'] = true;

    }

    public function downloadFile($params)
    {
        \XFILES::downloadFile($_SESSION['user']['lastFileToDownload']);
    }

    public function copyFiles($params)
    {
        if ($params['names']) {
            foreach ($params['names'] as $name) {
                $src = \xConfig::get('PATH', 'MEDIA') . $params['currentPath'] . '/' . $name;
                $dst = \xConfig::get('PATH', 'MEDIA') . $params['path'] . '/' . $name;

                if (is_dir($src)) {
                    MatrixFileManager::recurseCopy($src, $dst);
                } else {
                    copy($src, $dst);
                }
            }

            $this->result['copy'] = 1;
        }
    }

    public function recurseCopy($src, $dst)
    {
        $dir = opendir($src);
        @mkdir($dst);

        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->recurseCopy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }

        closedir($dir);
    }

    public function setIPTC($params)
    {

        include_once(\xConfig::get('PATH', 'EXT') . 'iptc/iptc.php');


        try {

            $iptc = new Iptc(\xConfig::get('PATH', 'MEDIA') . $params['filename']);

            $iptc->set(Iptc::CAPTION, base64_encode($params['description']));

            $iptc->set(Iptc::OBJECT_NAME, base64_encode($params['name']));

            $iptc->set(Iptc::EDIT_STATUS, $params['disable']);

            $iptc->set(Iptc::CAPTION_WRITER, $params['ownerEmail']);

            $iptc->write();


            XRegistry::get('EVM')->fire('fileManager:onIPTCSet', array('params' => $params));


        } catch (Iptc_Exception $e) {

            \connector::pushError($e->getMessage());

        }

    }


    public function getIPTC($params)

    {
        include_once(\xConfig::get('PATH', 'EXT') . 'iptc/iptc.php');

        try {

            $iptc = new \Iptc(\xConfig::get('PATH', 'MEDIA') . $params['filename']);

            $data['description'] = $iptc->fetch(\Iptc::CAPTION) ? base64_decode($iptc->fetch(\Iptc::CAPTION)) : '';

            $data['name'] = $iptc->fetch(\Iptc::OBJECT_NAME) ? base64_decode($iptc->fetch(\Iptc::OBJECT_NAME)) : '';

            $data['disable'] = $iptc->fetch(\Iptc::EDIT_STATUS) ? $iptc->fetch(\Iptc::EDIT_STATUS) : '';

            $data['ownerEmail'] = $iptc->fetch(\Iptc::CAPTION_WRITER) ? $iptc->fetch(\Iptc::CAPTION_WRITER) : '';

            $data['enabled'] = true;


        } catch (\Iptc_Exception $e) {
            \connector::pushError($e->getMessage());
            $data['enabled'] = false;
        }

        $this->result['iptc'] = $data;


    }

    public function getFile()
    {
        if (isset($_FILES["file"]) || is_uploaded_file($_FILES["file"]["tmp_name"]) || $_FILES["file"]["error"] == 0) {

            $fileName = \XCODE::translit($_FILES["file"]["name"]);

            $ext = substr($fileName, strrpos($fileName, '.') + 1);

            if (in_array($ext, $this->options['allowFilesTypes'])) {
                copy($_FILES["file"]["tmp_name"], \xConfig::get('PATH', 'MEDIA') . $_POST['path'] . '/' . $fileName);
            }


        } else {

            exit(0);
        }

        return true;
    }

}