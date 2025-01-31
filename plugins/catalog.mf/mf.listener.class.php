<?php

use X4\Classes\XNameSpaceHolder;
use X4\Classes\XRegistry;
use X4\Classes\MultiSection;
use X4\Classes\XCache;


class mfListener extends xListener  implements xPluginListener
{
	public $masks=array('goodmask'=>'{element}',
						'categorymask'=>'{element}',
						'subcategorymask'=>'{element}',
						'filteredcategorymask'=>'{element}'
						);
						
    public $descriptionMasks=array('goodmask'=>'{element}',
									'categorymask'=>'{element}',
									'subcategorymask'=>'{element}',
									'filteredcategorymask'=>'{element}'
						);						
						
				
	public function __construct()
    {
        
        parent::__construct('catalog.mf');        
        $this->_EVM->on('catalog.front:afterInit','afterModuleInit',$this);          
		$this->_EVM->on('catalog.setSeoData','setSeo',$this);          
		$this->_EVM->on('ishop.goodsToOrder:after','outerOrderSoap',$this);    
        $this->_EVM->on('catalog.onModuleCacheRead','onCacheRead',$this);       
        $this->_EVM->on('catalog.onModuleCacheWrite','onCacheWrite',$this);   
        $this->_EVM->on('catalog.showObject:objectReady','registerView',$this);                     
        $this->_EVM->on('agregator:end','setSub',$this);
		$this->_EVM->on('agregator:start','checkMultiSlash',$this);
        $this->_EVM->on('comments.addComment:after','commentLetterToAdmin',$this);
        $this->_EVM->on('catalog.property.currencyIshopProperty:beforeHandleTypeFront','brandDiscount',$this);
        $this->_EVM->on('catalog.seoplus:onDataSetted','dataSetted',$this);
	//	$this->_EVM->on('catalog.back:onObjectIndex','onObjectIndex',$this);
		

        $this->useModuleTplNamespace();
        $this->useModuleXfrontNamespace();
    }  
		
		
		public function onObjectIndex($params)
		{
			
		
		}
	
		
        public function dataSetted($params)
		{
			//if(!$params['data']['datasetted']&&(strstr($_SERVER['REQUEST_URI'],'/catalog/'))&&!empty($_GET)&&!$_GET['page'])
			
			
			
			if(!$params['data']['datasetted']&&(strstr($_SERVER['REQUEST_URI'],'/catalog/'))&&!empty($_GET)&&strstr($_SERVER['REDIRECT_URL'], '--'))
			{				
				xRegistry::get('TPA')->globalFields['extendedMeta']='<meta name="robots" content="noindex, nofollow"/>';					
			}
			
		}
		
        public function brandDiscount($params)
        {



            $pathIn='branddiscount/'.$params['data']['object']['obj_type'];
            $ext = XCache::serializedRead($pathIn, $params['data']['object']['id']);

            if(!empty($ext)) return $ext;

            $catalog=xCore::moduleFactory('catalog.front');
            $brand=$catalog->_tree->readNodeParam($params['data']['object']['netid'],'tovarbase.brand');

            if(!empty($brand))
            {
                $brand=json_decode($brand);
                $discount=$catalog->_tree->readNodeParam($brand[0],'selector.discount');
				if(!empty($discount))
				{                        
					$params['data']['object']['params']['price__in__BR'] =$params['data']['object']['params']['price__in__BR']*$discount;  
                    $params['data']['object']['params']['price'] =$params['data']['object']['params']['price']*$discount;  

                    if($params['data']['object']['params']['price__discount']) {
                        $params['data']['object']['params']['price__discount'] = $params['data']['object']['params']['price__discount'] * $discount;
                    }

					$x=array('object'=>$params['data']['object'],'value'=>$params['data']['object']['params']['price']);

					XCache::serializedWrite($x, $pathIn, $params['data']['object']['id']);

                    return $x;
					
				}
            }
            
                                  
            return false;
                
        }
   
	   public function checkMultiSlash($params)
	   {
	   
		
			if(strstr($_SERVER['QUERY_STRING'],'xoadCall'))return;
			
			
				if('?'==substr($_SERVER['REDIRECT_URL'], -1)&&$params['data']!='/')
				{
					 $link=substr($_SERVER['REDIRECT_URL'],0, -1);
                     xRegistry::get('TPA')->move301Permanent($link);  
				}
				
			
				
				if('/'==substr($params['data'], -1)&&$params['data']!='/')
                {                    
                    $link=substr($params['data'],0, -1);
                     xRegistry::get('TPA')->move301Permanent($link);  
				
                } 
	        
			if(strstr($params['data'],'//'))
            {
                xRegistry::get('TPA')->showError404Page();   
            }
			
	   
	   }
	   
	   
	  public function endsWith($haystack, $needle)
		{
			$length = strlen($needle);
			if ($length == 0) {
				return true;
			}

			return (substr($haystack, -$length) === $needle);
		}
		
		
    public function registerView($params)
    {     
        if(array_key_exists('viewed',$params['data']['object']['ourprojects']))
        {
            $viewed= $params['data']['object']['ourprojects']['viewed'];
            $viewed++;
            $module = xCore::loadCommonClass('catalog');                   
            $module->_tree->writeNodeParam($params['data']['object']['_main']['id'],'ourprojects.viewed',$viewed);  
                
        }
        
        
    }
    
    




    public function setSub()
    {
            $sub=xRegistry::get('TPA')->requestActionSub;
            if($sub=='showObject')xRegistry::get('TPA')->setGlobalField(array('useOneColumn'=>true));
    }
    
    public function onCacheWrite($params)    
    {
        if(xRegistry::get('TPA')->requestActionSub)
        {             
            $params['data']['cache']['subAction']= xRegistry::get('TPA')->requestActionSub;
        }
        
        return $params['data']['cache'];
    }
    
    public function onCacheRead($params)    
    {
         
         
        if($sub=$params['data']['cache']['cache']['subAction'])
        {      
           xRegistry::get('TPA')->requestActionSub= $sub;
           if($sub=='showObject')xRegistry::get('TPA')->setGlobalField(array('useOneColumn'=>true));
        }
        
        
          
    }
	
	private function getUpperName($object,$n=-1)
	{
			$length=count($object['_main']['path']);
			$id=$object['_main']['path'][$length+$n];
			return $this->_module->_tree->readNodeParam($id,'Name');
	}
	
		private function setDescriptionMask($element,$mask)
	{
		
		if($_GET['page']){$page=' | страница '.$_GET['page'];}						
		return str_replace('{element}',$element,$this->descriptionMasks[$mask]).$page;
		
	}
    
	private function setMask($element,$mask)
	{
		
		if($_GET['page'])
						{
							$page=' | страница '.$_GET['page'];
						}
						
		return str_replace('{element}',$element,$this->masks[$mask]).$page;
		
	}
	
    public function setSeo($params)
	{
			$object=$params['data']['object'];
			
			
			
			$this->_module = xCore::moduleFactory('catalog.front');
			$page='';
			
			if($_GET['page'])
						{
							$page=' | страница '.$_GET['page'];
						}
						
			
			
			if($_GET['page'])
			{
			
				 $object['seo']['Description']=trim($object['seo']['Description']);
				
				
				if(!empty($object['seo']['Description']))$object['seo']['Description'].=$page;
				
				
			}
				
				
			if($object['seo']['Title']&&!$_GET['f']['like'])
			{ 
				$object['seo']['Title'].=$page;			
				return $object;
			}
			
			
			
			switch(count($object['_main']['path']))
			{
					case 3:
					
						$subName=$this->getUpperName($object);					
						$elementDescription=$element=$subName.' - '.$object['_main']['Name'];
					    
						
						if(!empty($_GET['f']['like']))
						{
							list($k,$v)=each($_GET['f']['like']);
							$subLikeName=$this->_module->_tree->readNodeParam($v,'Name');
							$element.=' '.$subLikeName;	
							
							$elementDescription=$this->setDescriptionMask($element,'filteredcategorymask');
						
							$element=$this->setMask($element,'filteredcategorymask');
							
							
							
							
						}else{
						
						    $elementDescription=$this->setDescriptionMask($element,'subcategorymask');
							$element=$this->setMask($element,'subcategorymask');
							
						}
						
						
						$object['seo']['Description']=$elementDescription;
						$object['seo']['Title']=$element;
					
					break;
					
					
					case 2:
					
						
						$element=$object['_main']['Name'];
						$elementDescription=$this->setDescriptionMask($element,'categorymask');
					    $element=$this->setMask($element,'categorymask');
						
						$object['seo']['Description']=$elementDescription;
						$object['seo']['Title']=$element;
					
					break;
			
			}
			
			if($object['_main']['objType']=='_CATOBJ')
			{
					$element=$object['_main']['Name'];	
					$elementDescription=$this->setDescriptionMask($element,'categorymask');
					
					$object['seo']['Description']=$elementDescription;
					$object['seo']['Title']=$this->setMask($element,'goodmask');
			}
			
			return $object;
		
	}
	
     public function afterModuleInit($moduleInstance)
      {                             
      //  $this->defineFrontActions($moduleInstance['data']['instance']->_commonObj);   
      }
      
      
                                
      public function defineFrontActions(xCommonInterface $moduleInstance)
        {                
        //    $moduleInstance->defineAction('justViewed',array('callContext'=>$this,'priority'=>8));
        }

    public function sendEventMail($params){

        $template = $params['template'];
        $this->_module = xCore::moduleFactory('catalog.front');
        $this->_module->loadModuleTemplate($template);
        $this->_module->_TMS->addMassReplace('mail_body', $params);

        $m=xCore::incModuleFactory('Mail');

        if($params['sendto']){

            $to = $params['sendto'];

        }else{

            $to =xConfig::get('GLOBAL','admin_email');

        }

        $m->From( xConfig::get('GLOBAL','from_email'), xConfig::get('GLOBAL','site_encoding'));
        $m->To($to);
        $m->Content_type('text/html');
        $m->Subject($this->_module->_TMS->parseSection('mail_subject'), xConfig::get('GLOBAL','site_encoding'));
        $m->Body($this->_module->_TMS->parseSection('mail_body'));
        $m->Priority(2);
        $m->Send();

    }

    public function commentLetterToAdmin($params){
        $data = $params['data']['comment'];

        $id = $params['data']['cobject']['cid'];
        $editLink = 'http://'.$_SERVER['HTTP_HOST'].'/admin.php?#e/comments/edit_COBJECT/?id='.$id;
        $mailParams = array('template'=>'comment_to_admin_sendmail.html','name'=>$data['userName'],'email'=>$data['email'],'header'=>$data['header'],'message'=>$data['message'],'editLink'=>$editLink);

        $this->sendEventMail($mailParams);
    }
    
}
