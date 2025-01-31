<?php

   use X4\Classes\XNameSpaceHolder;
use X4\Classes\XRegistry;
use X4\Classes\MultiSection;
use X4\Classes\XCache;
                                                
class ratingcommentListener extends xListener  implements xPluginListener
{
    public function __construct()
    {
    
        parent::__construct('catalog.ratingcomment');        
        $this->_EVM->on('comments.addComment:after','registerRating',$this);        
        $this->_EVM->on('comments.onSaveEdited_COMMENT','refreshRate',$this);  
        $this->_EVM->on('comments.onSave_COMMENT','refreshRateOnSave',$this);          
        $this->_EVM->on('comments.deleteCommentsList:before','onDeleteRefreshBefore',$this);   
        $this->_EVM->on('comments.deleteCommentsList:after','onDeleteRefreshAfter',$this);   
        
        $this->useModuleTplNamespace();
        $this->catalogRatingProperty='tovarbase.rating';
    }  
    
     public function onDeleteRefreshAfter($params)
     {         
         if($this->lastComment)
         {
            $this->ratingSet($this->lastComment['cid'],$this->lastCobj['params']['cobjectId']);
         }
            
     }
     
    public function onDeleteRefreshBefore($params)
    {
    
          $objInstance = xCore::loadCommonClass('comments');
          
          if($id=$params['data']['delete'][0])
          {
            $this->lastComment=$objInstance->getComment($id);   
            $this->lastCobj=$objInstance->_tree->getNodeInfo($this->lastComment['cid']);                  
          }
    
        
    }
    
    public function refreshRateOnSave($params) 
    {
        $cobj=$objInstance->_tree->getNodeInfo($params['id']);        
        $this->ratingSet($params['id'],$cobj['params']['cobjectId']);
    }   
    
    public function refreshRate($params)    
    {        
        
        $objInstance = xCore::loadCommonClass('comments');
        $comment=$objInstance->getComment($params['data']['commentId']);        
        $cobj=$objInstance->_tree->getNodeInfo($comment['cid']);        
        $this->ratingSet($comment['cid'],$cobj['params']['cobjectId']);
        
        
    }
  
    public function registerRating($params)
    {
                        
               $this->ratingSet($params['data']['cobject']['cid'],$params['data']['cobject']['cobjectId']);
           
    }
    
    public function ratingSet($cid,$cobjectId)
    {
    
			if($result=xRegistry::get('XPDO')->query('SELECT AVG( rating ) AS rate FROM comments WHERE replyId IS NULL and cid ='.$cid))
               {
                     $row=$result->fetch(PDO::FETCH_ASSOC);   
   
                    if($row['rate'])
                    {   
                       $catalog=xCore::moduleFactory('catalog.front');
                                                       
                       $catalog->_tree->writeNodeParam($cobjectId,$this->catalogRatingProperty,round($row['rate']));    //$row['rating'];
                    }
               }
        
    }
    
  
    
}