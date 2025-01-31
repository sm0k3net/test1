<?php

error_reporting(E_ALL);
session_start();

use X4\Classes\XRegistry;

require('boot.php');




class testCover extends X4\Classes\MultiSectionObject{


    public function __construct($objectCover){
            parent::__construct($objectCover,'testCover');
    }

    public function testme($params){
        return 'test';
    }

}


$objCover=new testCover(['test'=>2]);


XRegistry::get('TMS')->addFileSection('{%section:sample%} 
 
                {%F:#gallery(E:getPicturesFromFolder({"folder":"1"}))%}
 
                {%F:@cover->testme({"test2":"te2st"})%} 
 
    {%endsection:sample%}',true);

XRegistry::get('TMS')->addReplace('sample','cover',$objCover);

echo XRegistry::get('TMS')->parseSection('sample');


