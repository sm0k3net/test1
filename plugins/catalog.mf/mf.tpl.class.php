<?php

use X4\Classes\XNameSpaceHolder;
use X4\Classes\XRegistry;
use X4\Classes\MultiSection;
use X4\Classes\XCache;


class mfTpl
    extends xTpl
    implements xPluginTpl
{
    var $assembleMatrix;

    public function __construct() {
        parent::__construct('catalog.mf');

    }

    public function roleAccessCheckObject($params)
    {
        $idGroupBase = $params['object']['_main']['path'][2];
        $group = $this->_module->_tree->selectAll()->where(array('@id', '=', $idGroupBase))->singleResult()->run();
        $rolesGroupIds = json_decode($group['params']['gruppa.roleAccess'],true);
        $userRoles = $_SESSION['siteuser']['userdata']['roles'];
        if(empty($rolesGroupIds) || in_array($userRoles,$rolesGroupIds))
        {
            return true;
        }
        return false;
    }
    public function roleAccessCheck($params)
    {
        $roleAccess = $params['gruppa']['roleAccess'];
        if(empty($roleAccess))
        {
            return true;
        }
        if(!isset($roleAccess[0]))
        {
            $rolesGroup[] = $roleAccess;
        }
        else
        {
            $rolesGroup = $roleAccess;
        }
        $rolesGroupIds = [];
        foreach($rolesGroup as $key => $val)
        {
            $rolesGroupIds[] = $val['_main']['id'];
        }
        $userRoles = $_SESSION['siteuser']['userdata']['roles'];
        if(in_array($userRoles,$rolesGroupIds))
        {
            return true;
        }
        return false;
    }

    public function ifUserActive()
    {
        if($_SESSION['siteuser']['authorized'])
        {
            return true;
        }
        else
        {
            return false;
        }
    }
    public function getUserImageRand($params)
    {
        $files = $this->getGalleryFiles($params);
        if($files['count']>0)
        {
            $files['count']--;
            $num = rand(0,$files['count']);
            $file = $files['arrFils'][$num];
            if(is_file($_SERVER['DOCUMENT_ROOT'].$file))
            {
                return $file;
            }
        }
        return false;
    }

    public function getGalleryFiles($params){
        $files=ENHANCE::getPicturesFromFolder($params);
        if (!empty($files)){
            $arr = array();

            foreach($files as $img)
            {
                $arr[] = $img['image'];
            }
            sort($arr);
            $arr2=array();
            $i=0;
            foreach($arr as $img)
            {
                $i++;
                $arr2[$i] = array('image'=>$img);
            }
            return array("files"=>$arr2, "arrFils"=>$arr, "count"=>count($files));
        }
    }

    public function getPageAgree($params)
    {
        $params['idUser'] = $_SESSION['siteuser']['id'];
        $query = "SELECT * FROM `seePageAgree` WHERE `idUser`='{$params['idUser']}' AND `idPage` = '{$params['idPage']}'";
        $pdoResult = XRegistry::get('XPDO')->query($query);
        $pdoResult = $pdoResult->fetchAll(PDO::FETCH_ASSOC);
        if(is_array($pdoResult) && count($pdoResult)>0)
        {
            return false;
        }
        else
        {
            return true;
        }
    }
    public function getUserInfo()
    {
        $fuser = xCore::moduleFactory('fusers.front');
        $returnArray['authorized'] = $_SESSION['siteuser']['authorized'];
        $returnArray['fullname'] = "Гость";
        if(!$returnArray['authorized'])
        {
            $returnArray['authorized'] = false;
        }
        else
        {
            if ($user = $fuser->_tree->selectStruct('*')->selectParams('*')->where(array('@id', '=', $_SESSION['siteuser']['id']))->singleResult()->run())
            {
                $returnArray['userId'] = $user['id'];
                $returnArray['avatar'] = $user['params']['avatar'];
                $returnArray['fullname'] = $user['params']['surname'] . ' ' . $user['params']['name'] . ' ' . $user['params']['patronymic'];
            }
        }

        return $returnArray;
    }

    public function getFilesByIdList($params) //Руководства в карточке
    {
        $gal = array();
        $c = 1;
        foreach($params['files'] as $fileId)
        {
            if ($file = $this->_module->_tree->selectAll()->where(array('@id', '=', $fileId))->singleResult()->run())
            {
                $file = $this->_module->_commonObj->convertToPSG($file);
                $link = $file['file']['file'];
                $basename = $file['file']['name'];

                $filesize = filesize($_SERVER['DOCUMENT_ROOT'].$link);
                $filesize = $filesize / 1024;
                $filesize = round($filesize, 2);

                $info = new SplFileInfo($link);
                $ras = $info->getExtension();

                $ras = strtoupper($ras);

                $gal['files'][$c]['basename'] = $basename;
                $gal['files'][$c]['ras'] = $ras;
                $gal['files'][$c]['filesize'] = $filesize;
                $gal['files'][$c]['link'] = $link;
                $c++;
            }
        }
        $gal['count'] = count($gal['files']);
        return $gal;
    }

    public function getActiveProps($params)
    {
        static $propMatrix;


        if(!$propMatrix)
        {

            $propMatrix=array
            (
                3525=>array('length','height','articul')

            );




        }

        return $propMatrix[$params['pset']];

    }

    public function skuAsStringAnalyse($params)
    {


        if($skuUniq=xNameSpaceHolder::call('module.catalog.tpl' , 'skuUniq', $params))
        {


            $singles=XARRAY::arrToLev($skuUniq,'id','params',$params['param']);

            //return implode(', &nbsp;',$singles);
            return implode(', ',$singles);
        }

        return false;


    }

    public function determineSkuCut($params)
    {

        switch($params['skuLink'])
        {
            case '3527546':
                $skuUniq=xNameSpaceHolder::call('module.catalog.tpl' , 'skuUniq', array('sku'=>$params['sku'],"param"=>'coloronly'));
                break;

            case '3527633':
                $skuUniq=xNameSpaceHolder::call('module.catalog.tpl' , 'skuUniq', array('sku'=>$params['sku'],"param"=>'typekryshki'));
                break;

            case '3531625':
                $skuUniq=xNameSpaceHolder::call('module.catalog.tpl' , 'skuUniq', array('sku'=>$params['sku'],"param"=>'varparam'));
                break;

            case '3527546':
                $skuUniq=xNameSpaceHolder::call('module.catalog.tpl' , 'skuUniq', array('sku'=>$params['sku'],"param"=>'coloronly'));
                break;

			case '3552525':
                $skuUniq=xNameSpaceHolder::call('module.catalog.tpl' , 'skuUniq', array('sku'=>$params['sku'],"param"=>'coloronly'));

              break;



            default:

                $skuUniq=xNameSpaceHolder::call('module.catalog.tpl' , 'skuUniq', array('sku'=>$params['sku'],"param"=>array("width","height","length")));
                break;


        }

        return   $skuUniq;
    }

    public function calculateProjectSum($params)
    {


        if($params['objs'])
        {
            $ext['min']=0;
            foreach($params['objs'] as $obj)
            {



                $paramz=array("param"=>"price",'skuList'=>$obj['_sku']);

                if($minmax=xNameSpaceHolder::call('module.catalog.tpl' , 'getMinMaxIshopPrice', $paramz))
                {
                    $ext['min']+=	$minmax['min']['value'];
                    $ext['max']+=	$minmax['max']['value'];
                }

            }

            return $ext;
        }

    }

    public function docsCompile($params)
    {
        foreach($params['objectDocs'] as $key=> $doc)
        {

            if(!$doc)
            {
                $doc=$params['ancestorDocs'][$key];
            }

            $newDocs[$key]=$doc;



        }

        return $newDocs;

    }

    public function findRelativeCollection($params)
    {


        if($params['id']['_main']['id'])
        {


            $filter['filterPack']=array("f" => array("like" => array("tovarbase.collection" => $params['id']['_main']['id'])));
            $filter['startpage']=0;
            $filter['onpage']=200;

            if ($params['linkId'])
            {
                $filter['serverPageDestination']=$this->_module->createPageDestination($params['linkId']);
                $pages                          =xCore::loadCommonClass('pages');

                if ($module=$pages->getModuleByAction($params['linkId'], 'showCatalogServer'))
                {
                    $filter['showBasicPointId']=$module['params']['showBasicPointId'];
                }
            }




            if ($catObjects=$this->_module->selectObjects($filter))
            {
                shuffle($catObjects['objects']);

                $allCount = count($catObjects['objects']);

                $startSlice = $allCount > 16 ? rand(0, $allCount - 16) : 0;
                $sliceCount  = $allCount > 16 ? 16 : $allCount;

                $resArr =  array_slice($catObjects['objects'], $startSlice, $sliceCount);

                return  $resArr;
            }

        }

        return false;


    }

    public function getCartCountById($params)
    {
        $ishop=xCore::moduleFactory('ishop.front');
        $item =$ishop->cartStorage[$params['id']];

        if ($item['count'])
        {
            return $item['count'];
        }
        else
        {
            return 0;
        }
    }


    public function getCategory($params)
    {
        static $ancestors;
        if($params['path']){
            $id=array_pop($params['path']);

            if(!$s= $ancestors[$id])
            {
                $node=$this->_module->_tree->getNodeInfo($id);
                $s=$ancestors[$id]= $this->_module->_commonObj->convertToPSG($node);

            }

            return $s;
        }

    }

    public function getFileSizeFormatted($params)
    {

        return XFILES::formatSize(filesize(PATH_.$params['file']));
    }


    public function mergeConnected($params)
    {


        if(!$params['main'])$params['main']=array();
        if(!$params['ancestor'])$params['ancestor']=array();


        return $result = $params['main']+$params['ancestor'];


    }


    public function fileInfo($params)
    {

        $pi=pathinfo($params['file']);
        return $pi['extension'];

    }
    public function  cutSkuList($params)
    {

        if(count($params['skuList'])<=$params['count'])
        {
            $sku=$params['skuList'];
            $showButton=false;
        }else{

            $sku=array_slice($params['skuList'],0,$params['count']);
            $showButton=true;

        }

        return array('sku'=>$sku,'showButton'=>$showButton);
    }


    public function getSizeString($params)
    {

		//полотенцесушители

		if($params['link']=='3553502')
		{
			$sizeParams=array('height','width');

		}else{

			$sizeParams=array('length','width','height');
		}


        foreach($sizeParams as $skuParam)
        {
            if(isset($params['sku'][$skuParam])&&$params['sku'][$skuParam])
            {
                $skuString[]= $params['sku'][$skuParam];
            }
        }

        if($s= implode(' x ',$skuString))
        {
            return   $s;
        }else{
            return false;}



    }


    public function sortSizes($params)
    {
        $sorted=usort($params['sizes'], 'sortSubSizes');
        return $params['sizes'];
    }

    public function detectSaleNew($params)
    {
        if ($sku=$params['sku'])
        {
            foreach ($sku as $s)
            {
                if ($s['params']['types'] == 'новинка')
                {
                    $isnew=1;
                }
                elseif ($s['params']['types'] == 'акция')
                {
                    $isaction=1;
                }
            }

            $z=array
            (
                'new'    => $isnew,
                'action' => $isaction
            );

            return $z;
        }
    }

    public function fileGetAny($params)
    {
        if (xRegistry::get('ENHANCE')->fileExists($params))
        {
            return $params['file'];
        }
        else
        {
            $dir=dirname($params['file']);

            if ($files=XFILES::filesList(PATH_ . $dir, 'files', array
            (
                '.png',
                '.jpg',
                '.jpeg'
            )))
            {
                return str_replace(PATH_, '', $files[0]);
            }
            else
            {
                return '';
            }
        }
    }

    public function getColorsFiles($params)
    {
        static $fld;

        $params['sort']='natsort';

        if (!$files=$fld[md5($params['folder'])])
        {
            $files=$fld[md5($params['folder'])]=ENHANCE::getPicturesFromFolder($params);
        }

        if (isset($files))
        {
            foreach ($files as $file)
            {
                if (strstr($file['image'], '_' . $params['color'] . '_'))
                {
                    $newFileSet[]=$file;
                }
            }

            return $newFileSet;
        }
    }




    public function testMonoSku($params)
    {
        if(is_array($params['sku']))
        {

            $unshifted=array_shift($params['sku']);
            if($unshifted['params']['mono']){return true;}else{

                return false;
            }
        }


    }

    public function showOnFirstPageTextOnly($params)
    {
        $pInfo=xRegistry::get('TPA')->getRequestActionInfo();

        if (!isset($pInfo['requestData']['page']))
        {
            return $params['value'];
        }
        else
        {
            return '';
        }
    }


    public function getBanner($params)
    {
        static $cache;

        if(!$cache[$params['object']['_main']['ancestor']]){
			
            $cache[$params['object']['_main']['ancestor']]=$ancnode=$this->_module->_tree->getNodeInfo($params['object']['_main']['ancestor']);
			
	
			
			if(!$ancnode['params']['grouptovarbase.banner'])
			{
				
				 $cache[$params['object']['_main']['ancestor']]=$ancnode=$this->_module->_tree->getNodeInfo($ancnode['_main']['ancestor']);
				
			}
        
          }else{

            $ancnode=$cache[$params['object']['_main']['ancestor']];
        }



        if($params['i']==$ancnode['params']['grouptovarbase.bannerpos'])
        {

            return array(
                'banner' => $ancnode['params']['grouptovarbase.banner'],
                'link' => $ancnode['params']['grouptovarbase.bannerlink']
            );
        }
        return false;

    }
    
    
    public function getSeoTags($params)
    {        
        if(!empty($params['category']['gruppa']['seotagger']))
        {
            
            $seotags=$this->_module->_tree->selectAll()->where(array('@id','=',$params['category']['gruppa']['seotagger']))->run();

            if(!empty($seotags))
            {
              
                $seotags = $this->_module->_commonObj->convertToPSGAll($seotags);        
                return $seotags;        
            }
                
        }
        
        return false;
    }

    public function getChildNodes($params)
    {
        if ($params['id'])
        {
            $filter['filterPack']=array("f" => array("ancestor" => array("ancestor" => $params['id'])));

            if ($params['linkId'])
            {
                $filter['serverPageDestination']=$this->_module->createPageDestination($params['linkId']);
                $pages                          =xCore::loadCommonClass('pages');

                if ($module=$pages->getModuleByAction($params['params']['linkId'], 'showCatalogServer'))
                {
                    $filter['showBasicPointId']=$module['params']['showBasicPointId'];
                }
            }



            if ($catObjects=$this->_module->selectObjects($filter))
            {
                return $catObjects['objects'];
            }
        }
        else
        {
            return false;
        }
    }

    public function viewObj($params){
        $a=1;
    }

}



function sortSubSizes($a, $b)
{
    if ($a['params']['size'])
    {
        $asize=$a['params']['size'];
    }
    else
    {
        $asize=$a['size'];
    }

    if ($b['params']['size'])
    {
        $bsize=$b['params']['size'];
    }
    else
    {
        $bsize=$b['size'];
    }

    $a_arr=explode('-', $asize);
    $b_arr=explode('-', $bsize);

    foreach (range(0, 3)as $i)
    {
        if ((int)$a_arr[$i] < (int)$b_arr[$i])
        {
            return -1;
        }
        elseif ((int)$a_arr[$i] > (int)$b_arr[$i])
        {
            return 1;
        }
    }

    return -1;
}
?>
