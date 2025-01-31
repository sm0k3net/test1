<?php

use X4\Classes\XPDO;
use X4\Classes\TableJsonSource;

class seoplusBack  extends xPluginBack
{
    public function __construct($name, $module)
    {
        parent::__construct($name, $module);
    }
    
    
  
    
    public function onEdit_SEORULE($params)
    {        
        $data=XPDO::selectIN('*','seo_plus_rules',(int)$params['id']);        
        $this->result['data']=$data[0];        
    }
    
    
     public function deleteSeoPlus($params)
    {               
        if (is_array($params['id'])) {
            $id = implode($params['id'], "','");
            $w = 'id in (\'' . $id . '\')';
        } else {
            $w = 'id="' . $params['id'] . '"';
        }

        $query = 'delete from seo_plus_rules where ' . $w;

        if ($this->_PDO->query($query)) {
            $this->result['deleted'] = true;
        }
    }
    
    
    
    public function onSave_SEORULE($params)
    {                                
        XPDO::insertIN('seo_plus_rules',$params['data']);
        $this->pushMessage('seo-rule-saved');
        
    }
    
    
      public function onSaveEdited_SEORULE($params)
    {        
        XPDO::updateIN('seo_plus_rules',(int)$params['id'],$params['data']);
        $this->pushMessage('seo-rule-edited');        
    }
    
	

	 public function onSearchInModuleSeoPlus($params)
	 {

        $params['word'] = urldecode($params['word']);

        $source = Common::classesFactory('TableJsonSource', array());

        if (!$params['page']) $params['page'] = 1;

        $opt = array(
            'customSqlQuery' => "SELECT * FROM  `seo_plus_rules` WHERE link LIKE '%{$params['word']}%'",                        
            'table' => 'seo_plus_rules',
            'order' => array('id', 'desc'),
            'onPage' => 50,
            'idAsNumerator' => 'id',            
            'columns' => array
            (
                'id' => array(),                
                'link' => array(),
                'title' => array(),
                'description' => array()                
            )

        );

        $source->setOptions($opt);
         
		if (!$params['page']) $params['page'] = 1;
        $this->result = $source->createView($params['id'], $params['page']);

    }


      public function seoplusTable($params)
    {
    
        $source = new TableJsonSource();
        $params['onPage'] =50;
        
        $opt = array
        (
            'onPage' => $params['onPage'],
            'table' => 'seo_plus_rules',
            'order' => array
            (
                'id',
                'desc'
            ),
            'where' => $where,
            'idAsNumerator' => 'id',            
            'columns' => array
            (
                'id' => array(),                
                'link' => array(),
                'title' => array(),
                'description' => array()                
            )
        );

        $source->setOptions($opt);

        if (!$params['page']) $params['page'] = 1;
        $this->result = $source->createView($params['id'], $params['page']);

    }
}
