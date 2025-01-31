<?php

  session_start();

	$width = ($_SESSION['captcha_settings'][1]) ? $_SESSION['captcha_settings'][1] : 140;          //Ширина изображения
	$height = 60;                                       //Высота изображения
	$font_size = 16;                                   //Размер шрифта
	$let_amount = ($_SESSION['captcha_settings'][0]) ? $_SESSION['captcha_settings'][0] : 6;     //Количество символов, которые нужно набрать
	$fon_let_amount = 10;                               //Количество символов на фоне
	$font = "project/templates/_ares/fonts/opensans-webfont.ttf"; //Путь к шрифту

	$letters = array("1","2","3","4","5","6","7","8","9","0","a","b","c","d","e","f","g","h","i","j","k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z");  //набор символов
	$colors = array("50","90","110","130","150","170","190");      //цвета

	$src = imagecreatetruecolor($width,$height);                    //создаем изображение
	$fon = imagecolorallocate($src,255,255,255);                    //создаем фон
	imagefill($src,0,0,$fon);                                       //заливаем изображение фоном

	for($i=0;$i<$fon_let_amount;$i++)                               //добавляем на фон буковки
	{
	  $color = imagecolorallocatealpha($src,rand(0,255),rand(0,255),rand(0,255),100);   //случайный цвет
	  $letter = $letters[rand(0,sizeof($letters)-1)];                                   //случайный символ
	  $size = rand($font_size-2,$font_size+2);                                          //случайный размер
	  imagettftext($src,$size,rand(0,45),rand($width*0.1,$width-$width*0.1),rand($height*0.2,$height),$color,$font,$letter);
	}

	for($i=0;$i<$let_amount;$i++)                                   //то же самое для основных букв
	{
	  $color = imagecolorallocatealpha($src,$colors[rand(0,sizeof($colors)-1)],
	  $colors[rand(0,sizeof($colors)-1)],$colors[rand(0,sizeof($colors)-1)],rand(20,40));
	  $letter = $letters[rand(0,sizeof($letters)-1)];
	  $size = rand($font_size*2-2,$font_size*2+2);
	  $x = ($i+1)*$font_size + rand(1,5);                           //даем каждому символу случайное смещение
	  $y = (($height*2)/3) + rand(0,5);

	  $cod[] = $letter;                                             //запоминаем код
	  imagettftext($src,$size,rand(0,15),$x,$y,$color,$font,$letter);
	}

    //переводим код в строку
	$cod = implode("",$cod);
        if(!empty($_GET['fid'])){
            if(!isset($_SESSION['captcha']) || is_string($_SESSION['captcha'])) {
				$_SESSION['captcha'] = array();
			}
            $fid = trim($_GET['fid']);
            $_SESSION['captcha'][$fid] = $cod;
        } else {
            $_SESSION['captcha'] = $cod;
        }

header ("Content-type: image/jpeg");                             //выводим готовую картинку
imagejpeg($src);

