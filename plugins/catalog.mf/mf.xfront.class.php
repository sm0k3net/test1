<?php
   use X4\Classes\XNameSpaceHolder;
use X4\Classes\XRegistry;
use X4\Classes\MultiSection;
use X4\Classes\XCache;
use X4\Classes\SimpleMail;


class mfXfront
    extends mfFront
    {
    
    
    public function __construct()
    {
        parent::__construct();
        $this->fusersApi = xCore::moduleFactory('fusers.api.json');
        $this->fuser = xCore::moduleFactory('fusers.front');
    }

    public function unAuthUserXfront($params) {//Выйти из кабинета
        session_start();
        $_SESSION['siteuser']['id'] = null;
        $_SESSION['siteuser']['userGroup'] = null;
        $_SESSION['siteuser']['userGroupName'] = null;
        $_SESSION['siteuser']['authorized'] = false;
        $_SESSION['siteuser']['userdata'] = null;
        $_SESSION['siteuser']['userdata']['login'] = null;
    }

    public function seePageAgree($params)
    {
        $params['idUser'] = $_SESSION['siteuser']['id'];
        $query = "INSERT INTO `seePageAgree` (idUser, idPage) VALUES ('{$params['idUser']}','{$params['idPage']}')";
        XRegistry::get('XPDO')->query($query);
        $this->result['success'] = true;
    }

     public function getPageAgreeList($params)
    {
        if($params['option']=='id')
        {
            $text = '';
            $query = "SELECT * FROM `seePageAgree` WHERE `idPage`='{$params['login']}'";
            $pdoResult = XRegistry::get('XPDO')->query($query);
            $pdoResult = $pdoResult->fetchAll(PDO::FETCH_ASSOC);
            if (is_array($pdoResult) && count($pdoResult) > 0) {
                $usersArr = array();
                foreach ($pdoResult as $info)
                {
                    $usersArr[] = $info['idUser'];
                }
                $users = $this->fuser->_tree->selectStruct('*')->selectParams('*')->where(array('@id', '=', $usersArr))->run();
                foreach ($users as $info)
                {
                    $text .= " - {$info['params']['name']} {$info['params']['surname']}<br><br>";
                }
                $this->result['success'] = true;
                $this->result['code'] = 200;
                $this->result['text'] = $text;
                return;
            } else {
                $this->result['success'] = false;
                $this->result['code'] = 304;//Нет страниц
                return;
            }
        }
        else if($params['option']=='login' || empty($params['option'])) {
            if ($user = $this->fuser->_tree->selectStruct('*')->selectParams('*')->where(array('@basic', '=', $params['login']))->singleResult()->run()) {
                $text = '';

                $query = "SELECT * FROM `seePageAgree` WHERE `idUser`='{$user['id']}'";
                $pdoResult = XRegistry::get('XPDO')->query($query);
                $pdoResult = $pdoResult->fetchAll(PDO::FETCH_ASSOC);
                if (is_array($pdoResult) && count($pdoResult) > 0) {
                    foreach ($pdoResult as $info) {
                        if ($page = $this->_module->_tree->selectAll()->where(array('@id', '=', $info['idPage']))->singleResult()->run()) {
                            $text .= " - {$page['params']['Name']}<br><br>";
                        }

                    }
                    $this->result['success'] = true;
                    $this->result['code'] = 200;
                    $this->result['text'] = $text;
                    return;
                } else {
                    $this->result['success'] = false;
                    $this->result['code'] = 304;//Нет страниц
                    return;
                }
            }
        }
        else
        {
            $this->result['success'] = false;
            $this->result['code'] = 404;//Ошибка
            return;
        }

    }

    public function auth($params)//авторизация
    {
        $result = $this->fusersApi->login(NULL, $params);
        if($result['authorized']===true)
        {
            $pass = md5(strrev($params['password']));
            $result = $this->authInfoInsert(array('user' => $result['user'], 'pass' => $pass));
        }
        $this->result['success'] = $result;
        return $result;
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
    public function authInfoInsert($params)//Проверяем подошли ли данные и заносим структуру
    {
        if(!empty($params['user']) && !empty($params['pass']))
        {
            $user = $params['user'];
            $id = $user['id'];

                if ($user['params']['password'] == $params['pass']) {
                    session_start();
                    $avatar = $this->getUserImageRand(array("folder"=>"/project/templates/opsio.bi/_ares/images/users/"));
                    $this->fuser->_tree->writeNodeParams($id,array('avatar'=>$avatar));
                    $_SESSION['siteuser']['id'] = $user['id'];
                    $_SESSION['siteuser']['userGroup'] = $user['ancestor'];
                    $_SESSION['siteuser']['userGroupName'] = $this->fuser->_tree->readNodeParam($user['ancestor'], 'Name');
                    $_SESSION['siteuser']['authorized'] = true;
                    $_SESSION['siteuser']['userdata'] = array(
                        'site' => true,
                        'id' => $user['id'],
                        'name' => $user['params']['name'],
                        'surname' => $user['params']['surname'],
                        'lastname' => $user['params']['patronymic'],
                        'phone' => $user['params']['phone'],
                        'email' => $user['params']['email'],
                        'roles' => $user['params']['roles'],
                        'avatar' => $avatar,

                    );

                    $_SESSION['siteuser']['userdata']['login'] = $user['basic'];
                    $cart = $_SESSION['siteuser']['cart'];
                    XRegistry::get('EVM')->fire($this->fuser->_moduleName . '.userLogin', array('userData' => $_SESSION['siteuser']));
                    $_SESSION['siteuser']['cart'] = $cart;
                    return true;

            }
        }
        return false;
    }

    public function like($params)
    {
              
        $good=(int)$this->_module->_tree->readNodeParam($params['id'],'ourprojects.good');        
        $good++;
        $this->_module->_tree->writeNodeParam($params['id'],'ourprojects.good',$good);        
        
        
    }
    
	
	
	public function sendContactFormFeedback($params)
    {
		$this->_module->loadModuleTemplate('contactFormFeedback.html');
        $this->_module->_TMS->addMassReplace('mail_body', $params);
        $m=xCore::incModuleFactory('Mail');           
        $m->From('sender@sanline.by');
        $m->To('6666750@gmail.com');
        $m->Content_type('text/html');
        $m->Subject($this->_module->_TMS->parseSection('mail_subject'));        
        $m->Body($this->_module->_TMS->parseSection('mail_body'),xConfig::get('GLOBAL','siteEncoding'));
        $m->Priority(2);
        $m->Send();
		
		$this->result['true']=1;
		
        
    }
	

	
	public function sendGarantForm($params)
    {
		$this->_module->loadModuleTemplate('sendGarantForm.html');
        
		$this->_module->_TMS->addMassReplace('mail_body', $params);
        
        
		$my_name = "Sanline";
		$my_mail = "abiatop@gmail.com";
		$my_replyto = "6666750@gmail.com";
		$my_subject = $this->_module->_TMS->parseSection('mail_subject');
		$my_message = $this->_module->_TMS->parseSection('mail_body');
		

	    $file=parse_url($params['fileupload']);				
		$file=$file['path'];
        $file=str_replace('//','/',$file);
		
		
		$file2=parse_url($params['fileupload2']);				
		$file2=$file2['path'];
        $file2=str_replace('//','/',$file2);
		
		
		
		$send = SimpleMail::make()
		->setTo($my_mail, $my_mail)
		->setFrom($my_mail, 'sanline')
		->setSubject('garant form')
		->setMessage($my_message)								
		->setWrap(100)		 
		->addAttachment(PATH_.$file)		
		->addAttachment(PATH_.$file2)
		->send();
		
		
	
		/*$m->From('sender@sanline.by');
        $m->To('tech@abiatec.com');
        $m->Content_type('text/html');
		
		$type=exif_imagetype($file);
		$type=image_type_to_mime_type($type);
		$file=str_replace('//','/',$file);
		$m->Attach($file,$type, 'attachment');
        $m->Subject($this->_module->_TMS->parseSection('mail_subject'));        
        $m->Body($this->_module->_TMS->parseSection('mail_body'),xConfig::get('GLOBAL','siteEncoding'));		
        $m->Priority(2);
		
        $m->Send();*/

		$this->result['true']=1;
		
        
    }
	
	public function sendContactForm($params)
    {
		$this->_module->loadModuleTemplate('contactForm.html');
        $this->_module->_TMS->addMassReplace('mail_body', $params);
        $m=xCore::incModuleFactory('Mail');           
        $m->From('sender@sanline.by');
        $m->To('6666750@gmail.com');
        $m->Content_type('text/html');
        $m->Subject($this->_module->_TMS->parseSection('mail_subject'));        
        $m->Body($this->_module->_TMS->parseSection('mail_body'),xConfig::get('GLOBAL','siteEncoding'));
        $m->Priority(2);
        $m->Send();
		
		$this->result['true']=1;
		
        
    }
	
	 public function sameimage($params)
    {
		
		$this->result['image']=xRegistry::get('ENHANCE')->imageTransform(array("r"=>array("w"=>"96")),array('value'=>$params['sameimage']));    
        
    }
	
    public function dislike($params)
    {
        $bad=(int)$this->_module->_tree->readNodeParam($params['id'],'ourprojects.bad');        
        $bad++;
        $this->_module->_tree->writeNodeParam($params['id'],'ourprojects.bad',$good);        
        
    }
        
                
    }
?>