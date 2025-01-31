  seoplusBack = new Class({
    Extends: _xModuleBack,
    initialize: function (name) {

        this.setName(name);
        this.parent();
        this.setLayoutScheme('listView', {});
        this.connector.module='plugin.catalog.seoplus';
        
    },

    
    
    onModuleInterfaceBuildedAfter:function()
    {        
         
           
     
      
    },
    
    onHashDispatch:function(e,v)
    {
        this.tabs.makeActive('t'+e);
        return true;          
    },
 

    CRUN:function()
    {        
       
    },
    
      
    start:function(){
            
        AI.navigate(AI.navHashCreate('catalog.seoplus','showSeoPlusList'));
     
    },
    
    
    create_SEORULE:function()
    {
        tpl=TH.getTpl('catalog.seoplus','create_SEORULE');
        this.setMainViewPort(tpl);
        this.validated= this.mainViewPortFind('#create_SEORULE').validationEngine();
        this.mainViewPortFind('.save').click(this.onSave_SEORULE.bind(this)); 
        
    },

    
    onSave_SEORULE:function(e)
    {
       
        e.preventDefault();
        
        if (this.validated) {
            data = xoad.html.exportForm("create_SEORULE");            
            saveObject = {                
                data: data
            };
            
            this.connector.execute({
                onSave_SEORULE: saveObject
            });
            
            AI.navigate(AI.navHashCreate('catalog.seoplus','showSeoPlusList'));
            
        }
        
    },
    
     onSaveEdited_SEORULE:function(e)
    {
       
        e.preventDefault();
        
        if (this.validated) {
            data = xoad.html.exportForm("edit_SEORULE");            
            saveObject = {    
                id:this.id,           
                data: data
            };
            
            this.connector.execute({
                onSaveEdited_SEORULE: saveObject
            });
            
        }
        
    },
  
  
  
    
    tabsStart:function()
    {
        
           var oTabs = [{
            id: 't_seorulesFirstpage',
            name:AI.translate('seoplus','rules_list'),
            temporal: false,
            active: true,
            href: AI.navHashCreate('catalog.'+this.name, 'showSeoPlusList')
        }, 
        {
            id: 'tcreate_SEORULE',
            name:AI.translate('common','add'),        
            href: AI.navHashCreate('catalog.'+this.name, 'create_SEORULE')
        }];

        this.tabs = new Tabs(this.tabsViewPort, oTabs);
        
    },
    
	
	
	 searchInModule: function (params) {
            this.connector.execute({onSearchInModuleSeoPlus: params});

            if (Object.getLength(this.connector.result) > 0) {
                if (typeof this.onSearchInModule == 'function') {
                    this.onSearchInModule(this.connector.result.searchResult);
                }
            }
        },

		
	onSearchInModule:function(result)
    {
    
	       this.tabs.addTab({
                    id: 'tshowSeoPlusSearchResults',
                    name: AI.translate('seoplus', 'search-results'),
                    temporal: true,
                    active: true
                }, true);
				
        this.setGridView('showSeoPlusList',(window.screen.availHeight-300),true);
       
        menu = new dhtmlXMenuObject();
        menu.renderAsContextMenu();
        
    
        menu.addNewChild(menu.topId, 0, "delete", AI.translate('common','delete'), false, '', '', this.deleteRules.bind(this));
        menu.addNewChild(menu.topId, 0, "edit", AI.translate('common','edit'), false, '', '',
     
                function(bid,kid)
                {
					cell=this.gridlist.cellById(kid,0);
                    AI.navigate(AI.navHashCreate('catalog.seoplus','edit_SEORULE',{id:cell.getValue()}));
                    
                }.bind(this)
                
        );
        

        this.gridlist = new dhtmlXGridObject('showSeoPlusList');
        this.gridlist.selMultiRows = true;
        this.gridlist.enableMultiline(true);
        this.gridlist.setImagePath("/x4/adm/xres/ximg/green/");
        this.gridlist.setHeader('id,' + AI.translate('common', 'link')  + ',' + AI.translate('common', 'title') + ',' + AI.translate('common', 'description'));
        this.gridlist.setInitWidths("80,350,300,*");
        this.gridlist.setColAlign("center,left,left,left,left");

        this.gridlist.attachEvent("onRowDblClicked",function(kid)
        {
            cell=this.gridlist.cellById(kid,0);
            AI.navigate(AI.navHashCreate('catalog.seoplus','edit_SEORULE',{id:cell.getValue()}));
            
        }.bind(this));    
        
        this.gridlist.setColTypes("ro,ro,ro,ro");
        this.gridlist.enableAutoWidth(true);
        this.gridlist.enableContextMenu(menu);  
        this.gridlist.init();
        this.gridlist.setSkin("modern");
        this.gridlist.onPage=50;
		
		
		
		 if (this.connector.result) {
            
            this.gridlist.parse(this.connector.result.data_set, "xjson")
        }
    
    },
    
    edit_SEORULE: function (params) 
    {
                
                this.tabs.addTab({
                    id: 'tshowSeoPlusListEdit',
                    name: AI.translate('seoplus', 'edit_SEORULE'),
                    temporal: true,
                    active: true
                }, true);
                
                  tpl=TH.getTpl('catalog.seoplus','create_SEORULE@edit');
                  this.setMainViewPort(tpl);
                  this.validated= this.mainViewPortFind('#edit_SEORULE').validationEngine();
      
                  this.id=  params.id;
                  result=this.execute({onEdit_SEORULE:{id:params.id}});
                  
                  this.mainViewPortFind('.save').click(this.onSaveEdited_SEORULE.bind(this));                  
                  xoad.html.importForm("edit_SEORULE",result.data);                  

    },
      
    
    
     deleteRules:function(kid,id)
    {
        
        selected = this.gridlist.getSelectedRowId(true);
        if(selected.length>0)
        {
            cells=[];
            for(i=0;i<selected.length;i++)
            {
                cell=this.gridlist.cellById(selected[i],0);
                cells.push(cell.getValue());    
            }
            
             this.execute({deleteSeoPlus:{id:cells}});            
        }
            
        if(this.connector.result.deleted)
        {
             this.gridlist.deleteSelectedRows();    
        }
        
    },
    
    
    showSeoPlusList: function (data) {
             
        this.setGridView('showSeoPlusList',(window.screen.availHeight-300),true);
       
        menu = new dhtmlXMenuObject();
        menu.renderAsContextMenu();
        
    
        menu.addNewChild(menu.topId, 0, "delete", AI.translate('common','delete'), false, '', '', this.deleteRules.bind(this));
        menu.addNewChild(menu.topId, 0, "edit", AI.translate('common','edit'), false, '', '',
     
                function(bid,kid)
                {
					cell=this.gridlist.cellById(kid,0);
                    AI.navigate(AI.navHashCreate('catalog.seoplus','edit_SEORULE',{id:cell.getValue()}));
                    
                }.bind(this)
                
        );
        

        this.gridlist = new dhtmlXGridObject('showSeoPlusList');
        this.gridlist.selMultiRows = true;
        this.gridlist.enableMultiline(true);
        this.gridlist.setImagePath("/x4/adm/xres/ximg/green/");
        this.gridlist.setHeader('id,' + AI.translate('common', 'link')  + ',' + AI.translate('common', 'title') + ',' + AI.translate('common', 'description'));
        this.gridlist.setInitWidths("80,350,300,*");
        this.gridlist.setColAlign("center,left,left,left,left");

        this.gridlist.attachEvent("onRowDblClicked",function(kid)
        {
            cell=this.gridlist.cellById(kid,0);
            AI.navigate(AI.navHashCreate('catalog.seoplus','edit_SEORULE',{id:cell.getValue()}));
            
        }.bind(this));    

        
        this.gridlist.setColTypes("ro,ro,ro,ro");
        this.gridlist.enableAutoWidth(true);
        this.gridlist.enableContextMenu(menu);  
        this.gridlist.init();
        this.gridlist.setSkin("modern");
        this.gridlist.onPage=40;
        
        this.listRules(data.id,data.page); 
         
         var  pg = new paginationGrid(this.gridlist, {
            target: this.mainViewPortFind('.paginator'),
            pages: this.connector.result.pagesNum,
            url: AI.navHashCreate('catalog.seoplus','showSeoPlusList', {id:data.id}) //,

        });
                

    },
	
	   onModuleSearchClick: function (e) {

            e.preventDefault();
            word=this.viewPort.find('.searchInModuleInput').val();

            if ('' != word) {
                AI.navigate(AI.navHashCreate('catalog.seoplus', 'searchInModule', {'word': encodeURIComponent(word)}));

            } else {

                alert(AI.translate('common', 'enter-any-word-to-search'));
            }

        },
		
		

 
    listRules: function (id,page) 
    {
        this.connector.execute({
            seoplusTable: {
                id:id,            
                page: page,
                onPage: this.gridlist.onPage
            }
        });
      
        if (this.connector.result.data_set) {
            
            this.gridlist.parse(this.connector.result.data_set, "xjson")
        }

    },
   
    buildInterface: function () 
    {
        this.parent();       
        this.tabsStart(); 
		
        
    }
    
 

});
    
        