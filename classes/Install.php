<?php
namespace X4\Classes;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;



class Install
{

    public $pagesTree;
    public $moduleInstalls=array();
    public $installedDomain;
    
    public function __construct()
    {
        $this->pagesTree = new XTreeEngine('pages_container', xRegistry::get('XPDO'));
    }

    public function createInitialFolders($permissions){
    
                    $fileSystem = new Filesystem();
                    
                    try {          
                    
                    if(!$fileSystem->exists(PATH_.'logs')){              
                            $fileSystem->mkdir(PATH_.'logs',$permissions);
                    }
                    
                    if(!$fileSystem->exists(\xConfig::get('PATH','CACHE'))){              
                            $fileSystem->mkdir(\xConfig::get('PATH','CACHE'),$permissions);
                    }
                    
                    if(!$fileSystem->exists(\xConfig::get('PATH','CACHE').'imagecache')){              
                            $fileSystem->mkdir(\xConfig::get('PATH','CACHE').'imagecache',$permissions);
                    }
                    
                    if(!$fileSystem->exists(\xConfig::get('PATH','MEDIA'))){
                        $fileSystem->mkdir(\xConfig::get('PATH','MEDIA'),$permissions);
                    }
                        
                    } catch (IOExceptionInterface $exception) {
                        
                        echo "An error occurred while creating your directory at ".$exception->getPath();
                    }
        
    }

    public function getCurrentInstalledDomains()
    {
        return $this->pagesTree->selectStruct('*')->selectParams('*')->where(array('@obj_type', '=', '_DOMAIN'))->run();
    }

    
    public function runModuleInstallers()
    {
        $modules = \xCore::discoverModules();
        
        if (!empty($modules)) {
            foreach ($modules as $module) {


                $installClass = \xConfig::get('PATH','MODULES').$module['name'].'/install/'.$module['name'].'.install.php';

                if (file_exists($installClass)) 
                    {
                        \xCore::callCommonInstance($module['name']);
                        include_once $installClass;
                        $classname = $module['name'].'Install';
                        $this->moduleInstalls[$module['name']] = new $classname();
                        $this->moduleInstalls[$module['name']]->run($this->installedDomain);
                    }
            }
        }
    
    }

    public function transformDomains($domains)
    {

        $installedDomains = $this->getCurrentInstalledDomains();
        $installedDomains = \XARRAY::arrToKeyArr($installedDomains, 'basic', 'id');

        foreach ($domains as $src => $dest) {
            if ($id = $installedDomains[$src]) {
                if (\xCore::checkHostDomain($dest)) {
                    $this->pagesTree->setStructData($id, 'basic', $dest);
                    $this->installedDomain=$dest;
                } else {
                    $notAccessedDomain[] = $dest;
                }
            }

            if (isset($notAccessedDomain)) return $notAccessedDomain;

        }
    }
}
