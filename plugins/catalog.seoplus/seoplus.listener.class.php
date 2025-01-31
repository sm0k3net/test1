<?php
use X4\Classes\xRegistry;

class seoplusListener extends xListener  implements xPluginListener
{
    public static $rules;
    public function __construct()
    {
        parent::__construct('catalog.seoplus');     
        
         $this->_EVM->on('agregator:onSetSeoData','setSeoPlusData',$this);
         $this->useModuleTplNamespace();
         $this->cacheRules();
    }

    public function cacheRules()
    {
        self::$rules=XCacheFileDriver::serializedRead('catalog-seoplus-seo-rules', 'all');
        if(empty(self::$rules)) {
            $dbh = xRegistry::get('XPDO');
            $stmt = $dbh->prepare("SELECT * from seo_plus_rules");
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as $row) {
                self::$rules[md5($row['link'])] = $row;
            }
            XCacheFileDriver::serializedWrite(self::$rules, 'catalog-seoplus-seo-rules', 'all');
        }
    }

    public static function getRule($link)
    {
        return  self::$rules[md5($link)];
    }

    public function setSeoPlusData($params)
    {
        	if($_GET['page'])
						{
							$page=' | страница '.$_GET['page'];
						}


        $result = self::getRule($_SERVER['REDIRECT_URL']);
        
        if(!empty($result))
        {
          $data['Title']=$result['title'].$page;
		  if($result['description'])$data['Description']=$result['description'].$page;
          $data['Keywords']=$result['keywords'];
		  $data['Canonical']=$result['canonical'];
		  
		  $dataSetted=true;			
		  
        }else{
			
			 $dataSetted=false;
		}
		
		 XRegistry::get('EVM')->fire('catalog.seoplus:onDataSetted', array('data' => $data,'datasetted'=>$dataSetted));        
		 
		 
		 if(strstr($_SERVER['REQUEST_URI'],'articles'))
		 {
			
			$data['Title']=$params['data']['Title'].$page;
			return $data;
		 }
		 
		  
		if($dataSetted)
		{
			return $data;	
		}

        
        return false;
        
    }
}
