<?php

namespace X4\Classes;

class ImageRenderer
{
    private $path;                  // входящий параметр -- путь к исходному изобранжению
    private $settings = array();    // входящий параметр -- настройки трансформаций
    private $imgHash;
    private $data;                  // начальные свойства исходного изображения: mime, width, height
    private $quality = 100;// $mime, $width, $height, $maxWidth, $maxHeight;
    private $errors = array();      // хранилище ошибок 
    private $url;                   // адрес обработанного файла (результат)
    private $image;                 // ресурс изображения
    private $cacheFileName;         // имя файла кеша (без пути и расширения)
    private $denyCaching = FALSE;   // TRUE -- не писать файлы в кеш, только генерировать на лету


    public function __construct($file, $settings, $imgHash = '')
    {
        if (!$file) {
            header("HTTP/1.1 404 Not Found");
            die();
        }
        $this->path = preg_replace('/^(s?f|ht)tps?:\/\/[^\/]+/i', '', (string)$file);
        $this->settings = $settings;
        $this->imgHash = $imgHash;
        $this->init();
        $this->generateCacheFileName();
        $this->readFileData();
        // Images must be local files, so for convenience we strip the domain if it's there

        if (!$this->check()) $this->returnError();
        if (!$this->transform()) $this->returnError();
        $this->output();
    }


    private function init()
    {


        define('MEMORY_TO_ALLOCATE', '50M');
        define('DEFAULT_QUALITY', 100);
        define('CURRENT_DIR', realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..\..'));
        define('CACHE_DIR_NAME', DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'imagecache' . DIRECTORY_SEPARATOR);
        define('CACHE_DIR', PATH_ . CACHE_DIR_NAME);
        define('DOCUMENT_ROOT', PATH_);
        $this->data['docRoot'] = preg_replace('/\/$/', '', DOCUMENT_ROOT); // Strip the possible trailing slash off the document root

        if (!file_exists(CACHE_DIR)) mkdir(CACHE_DIR, 0755, true);
    }

    private function check()
    {
        $d = CACHE_DIR;


        if (!isset($this->path))
            $this->error('HTTP/1.1 400 Bad Request', 'Error: no image was specified');
        if (!is_readable(CACHE_DIR))
            $this->error('HTTP/1.1 500 Internal Server Error', 'Error: the cache directory "' . CACHE_DIR . '" is not readable');
        if (!is_writable(CACHE_DIR))
            $this->error('HTTP/1.1 500 Internal Server Error', 'Error: the cache directory "' . CACHE_DIR . '" is not writable');

        // For security, directories cannot contain ':', images cannot contain '..' or '<', and images must start with '/'


        if ($this->path{0} != '/' || strpos(dirname($this->path), ':') || preg_match('/(\.\.|<|>)/', $this->path))
            $this->error('HTTP/1.1 400 Bad Request', 'Error: malformed image path. Image paths must begin with \'/\'');

        // If the image doesn't exist, or we haven't been told what it is, there's nothing that we can do
        if (!$this->path)
            $this->error('HTTP/1.1 400 Bad Request', 'Error: no image was specified');
        if (!file_exists($this->data['docRoot'] . $this->path))
            $this->error('HTTP/1.1 500 Internal Server Error', 'Error: image does not exist: ' . $this->data['docRoot'] . $this->path);
        if (substr($this->data['mime'], 0, 6) != 'image/')
            $this->error('HTTP/1.1 400 Bad Request', 'Error: requested file is not an accepted type: ' . $this->data['docRoot'] . $this->path);
        return true;
    }

    private function transform()
    {
        foreach ($this->settings as $t => &$params) {
            $transformation = ImageRendererUtils::$allowedGraphicMethods[$t];
            if (isset(ImageRendererUtils::$allowedGraphicTransformations[$t])
                && method_exists($this, $transformation)
                && is_array($params)
            ) {
                $this->$transformation($params);
            }
        }
    }

    private function error($status, $text)
    {
        ImageRendererUtils::dropError($status, $text);
    }

    private function returnError()
    {

    }

    private function output()
    {
        // Put the data of the resized image into a variable


        if (!$this->denyCaching && $this->cacheFileName) {
            $res = $this->data['outputFunction']($this->image, CACHE_DIR . $this->cacheFileName, $this->data['quality']);
        }


        ob_start();
        $this->data['outputFunction']($this->image, null, $this->data['quality']);

        $data = ob_get_contents();
        ob_end_clean();
        // Clean up the memory
        ImageDestroy($this->data['src']);
        ImageDestroy($this->image);
        //unset($_SESSION['imagecachedata'][$_GET['imghash']]);
        // See if the browser already has the image
        //$lastModifiedString = gmdate('D, d M Y H:i:s', filemtime($resized)) . ' GMT';
        //$etag               = md5($data);
        //doConditionalGet($etag, $lastModifiedString);

        // Send the image to the browser with some delicious headers
        header("Content-type: {$this->data['mime']}");
        header('Content-Length: ' . strlen($data));


        echo $data;

    }

    private function setTransparency()
    {
        if ($this->data['transparent']) {
            // If this is a GIF or a PNG, we need to set up transparency
            imagealphablending($this->image, false);
            imagesavealpha($this->image, true);
        }
    }

    private function readFileData($mime = null)
    {
        $d = GetImageSize($this->data['docRoot'] . $this->path);
        $this->data['width'] = (int)$d[0];
        $this->data['height'] = (int)$d[1];
        $this->data['mime'] = $d['mime'];
        $quality = $this->settings['quality'] ? $this->settings['quality'] : DEFAULT_QUALITY;

        switch ($d['mime'] ? $d['mime'] : 'image/jpeg') {
            case 'image/gif':
                // We will be converting GIFs to PNGs to avoid transparency issues when resizing GIFs
                // This is maybe not the ideal solution, but IE6 can suck it
                $this->data['creationFunction'] = 'ImageCreateFromGif';
                $this->data['outputFunction'] = 'ImagePng';
                $this->data['mime'] = 'image/png'; // We need to convert GIFs to PNGs
                $this->data['doSharpen'] = FALSE;
                $this->data['quality'] = round(10 - ($quality / 10)); // We are converting the GIF to a PNG and PNG needs a compression level of 0 (no compression) through 9
                $this->data['imgExtension'] = 'gif';
                $this->data['transparent'] = true;
                break;

            case 'image/x-png':
            case 'image/png':
                $this->data['creationFunction'] = 'ImageCreateFromPng';
                $this->data['outputFunction'] = 'ImagePng';
                $this->data['doSharpen'] = FALSE;
                $this->data['quality'] = round(10 - ($quality / 10));      // PNG needs a compression level of 0 (no compression) through 9
                $this->data['imgExtension'] = 'png';
                $this->data['transparent'] = true;
                break;

            default:
                $this->data['creationFunction'] = 'ImageCreateFromJpeg';
                $this->data['outputFunction'] = 'ImageJpeg';
                $this->data['doSharpen'] = TRUE;
                $this->data['imgExtension'] = 'jpg';
                $this->data['quality'] = $quality;
                $this->data['transparent'] = false;
                break;
        }

        ini_set('memory_limit', MEMORY_TO_ALLOCATE);                                // We don't want to run out of memory
        $this->data['src'] = $this->data['creationFunction']($this->data['docRoot'] . $this->path);     // Read in the original image
        $this->image = imagecreatetruecolor($this->data['width'], $this->data['height']);
        $this->setTransparency();


    }

    private function resize($params)
    {
        // $maxWidth,  $maxHeight                       -- в результате масштабирования габариты должны получиться не больше этих
        // $params['cratio'], $params['calign'],
        // $params['width'], $params['height']          -- переданные настройки масштабирования
        // $this->data['width'], $this->data['height']  -- габариты исходного изображения
        // $height, $width                              -- габариты исходного изображения
        // $ratioComputed                               -- соотношение сторон исходного изображения
        // $cropRatioComputed                           -- требуемое соотношение сторон
        $params['width'] = $params['w'];
        $params['height'] = $params['h'];
        $params['cratio'] = $params['r'];
        $params['calign'] = $params['a'];

        $width = $this->data['width'];
        $height = $this->data['height'];
        $maxWidth = $params['width'] ? $params['width'] : $width;
        $maxHeight = $params['height'] ? $params['height'] : $height;
        if (!$maxWidth && $maxHeight) $maxWidth = 99999999999999;
        elseif ($maxWidth && !$maxHeight) $maxHeight = 99999999999999;
        elseif (!$maxWidth && !$maxHeight) {
            $maxWidth = $this->data['width'];
            $maxHeight = $this->data['height'];
        }


        if (isset($params['cratio'])) {
            $cropOffsetPosition = $params['calign'] ? $params['calign'] : 'center';
            $cropRatio = explode('.', (string)$params['cratio']);
            if (count($cropRatio) == 2) {
                $ratioComputed = $this->data['width'] / $this->data['height'];
                $cropRatioComputed = (float)$cropRatio[0] / (float)$cropRatio[1];

                if ($ratioComputed < $cropRatioComputed) { // Image is too tall so we will crop the top and bottom
                    $origHeight = $this->data['height'];
                    $height = $width / $cropRatioComputed;
                    switch ($cropOffsetPosition) {
                        case 'top'    :
                            $offsetY = 0;
                            break;
                        case 'bottom' :
                            $offsetY = $origHeight - $height;
                            break;
                        default       :
                            $offsetY = ($origHeight - $height) / 2;
                            break;
                    }
                } else if ($ratioComputed > $cropRatioComputed) { // Image is too wide so we will crop off the left and right sides
                    $origWidth = $width;
                    $width = $height * $cropRatioComputed;
                    switch ($cropOffsetPosition) {
                        case 'left'  :
                            $offsetX = 0;
                            break;
                        case 'right' :
                            $offsetX = $origWidth - $width;
                            break;
                        default      :
                            $offsetX = ($origWidth - $width) / 2;
                            break;
                    }
                } else {
                    //      $offsetY = ($origHeight - $height) / 2;
                    //      $offsetX = ($origWidth - $width) / 2;
                }
            }
        }
        // Setting up the ratios needed for resizing. We will compare these below to determine how to
        // resize the image (based on height or based on width)
        $xRatio = $maxWidth / $width;
        $yRatio = $maxHeight / $height;

        if ($xRatio * $height < $maxHeight) { // Resize the image based on width
            $tnHeight = ceil($xRatio * $height);
            $tnWidth = $maxWidth;
        } else { // Resize the image based on height
            $tnWidth = ceil($yRatio * $width);
            $tnHeight = $maxHeight;
        }
        unset($this->image);
        $this->image = imagecreatetruecolor($tnWidth, $tnHeight);       // Set up a blank canvas for our resized image (destination)
        $this->setTransparency();


        ImageCopyResampled($this->image, $this->data['src'], 0, 0, (int)$offsetX, (int)$offsetY, (int)$tnWidth, (int)$tnHeight, $width, $height);
        $this->data['width'] = $width;
        $this->data['height'] = $height;
    }

    private function sharping($params)
    {
        $img = $this->image;
        $amount = $params['a'] ? $params['a'] : 80;
        $radius = $params['r'] ? $params['r'] : 0.5;
        $threshold = $params['t'] ? $params['t'] : 3;
        ////////////////////////////////////////////////////////////////////////////////////////////////
        ////          Unsharp Mask for PHP - version 2.1.1
        ////    Unsharp mask algorithm by Torstein Hønsi 2003-07.
        ////             thoensi_at_netcom_dot_no.
        ////               Please leave this notice.
        ///////////////////////////////////////////////////////////////////////////////////////////////
        // $img is an image that is already created within php using
        // imgcreatetruecolor. No url! $img must be a truecolor image.
        // Attempt to calibrate the parameters to Photoshop:
        if ($amount > 500) $amount = 500;
        $amount = $amount * 0.016;
        if ($radius > 50) $radius = 50;
        $radius = $radius * 2;
        if ($threshold > 255) $threshold = 255;
        $radius = abs(round($radius)); // Only integers make sense.
        if ($radius == 0) {
            return $img;

        }
        $w = imagesx($img);
        $h = imagesy($img);
        $imgCanvas = imagecreatetruecolor($w, $h);
        $imgBlur = imagecreatetruecolor($w, $h);
        // Gaussian blur matrix:
        //    1    2    1
        //    2    4    2
        //    1    2    1
        //////////////////////////////////////////////////
        if (function_exists('imageconvolution')) { // PHP >= 5.1
            $matrix = array(
                array(1, 2, 1),
                array(2, 4, 2),
                array(1, 2, 1)
            );
            imagecopy($imgBlur, $img, 0, 0, 0, 0, $w, $h);
            imageconvolution($imgBlur, $matrix, 16, 0);
        } else {
            // Move copies of the image around one pixel at the time and merge them with weight
            // according to the matrix. The same matrix is simply repeated for higher radii.
            for ($i = 0; $i < $radius; $i++) {
                imagecopy($imgBlur, $img, 0, 0, 1, 0, $w - 1, $h); // left
                imagecopymerge($imgBlur, $img, 1, 0, 0, 0, $w, $h, 50); // right
                imagecopymerge($imgBlur, $img, 0, 0, 0, 0, $w, $h, 50); // center
                imagecopy($imgCanvas, $imgBlur, 0, 0, 0, 0, $w, $h);
                imagecopymerge($imgBlur, $imgCanvas, 0, 0, 0, 1, $w, $h - 1, 33.33333); // up
                imagecopymerge($imgBlur, $imgCanvas, 0, 1, 0, 0, $w, $h, 25); // down
            }
        }
        if ($threshold > 0) {
            // Calculate the difference between the blurred pixels and the original
            // and set the pixels
            for ($x = 0; $x < $w - 1; $x++) { // each row
                for ($y = 0; $y < $h; $y++) { // each pixel
                    $rgbOrig = ImageColorAt($img, $x, $y);
                    $rOrig = (($rgbOrig >> 16) & 0xFF);
                    $gOrig = (($rgbOrig >> 8) & 0xFF);
                    $bOrig = ($rgbOrig & 0xFF);
                    $rgbBlur = ImageColorAt($imgBlur, $x, $y);
                    $rBlur = (($rgbBlur >> 16) & 0xFF);
                    $gBlur = (($rgbBlur >> 8) & 0xFF);
                    $bBlur = ($rgbBlur & 0xFF);
                    // When the masked pixels differ less from the original
                    // than the threshold specifies, they are set to their original value.
                    $rNew = (abs($rOrig - $rBlur) >= $threshold) ? max(0, min(255, ($amount * ($rOrig - $rBlur)) + $rOrig)) : $rOrig;
                    $gNew = (abs($gOrig - $gBlur) >= $threshold) ? max(0, min(255, ($amount * ($gOrig - $gBlur)) + $gOrig)) : $gOrig;
                    $bNew = (abs($bOrig - $bBlur) >= $threshold) ? max(0, min(255, ($amount * ($bOrig - $bBlur)) + $bOrig)) : $bOrig;
                    if (($rOrig != $rNew) || ($gOrig != $gNew) || ($bOrig != $bNew)) {
                        $pixCol = ImageColorAllocate($img, $rNew, $gNew, $bNew);
                        ImageSetPixel($img, $x, $y, $pixCol);
                    }
                }
            }
        } else {
            for ($x = 0; $x < $w; $x++) { // each row
                for ($y = 0; $y < $h; $y++) { // each pixel
                    $rgbOrig = ImageColorAt($img, $x, $y);
                    $rOrig = (($rgbOrig >> 16) & 0xFF);
                    $gOrig = (($rgbOrig >> 8) & 0xFF);
                    $bOrig = ($rgbOrig & 0xFF);
                    $rgbBlur = ImageColorAt($imgBlur, $x, $y);
                    $rBlur = (($rgbBlur >> 16) & 0xFF);
                    $gBlur = (($rgbBlur >> 8) & 0xFF);
                    $bBlur = ($rgbBlur & 0xFF);
                    $rNew = ($amount * ($rOrig - $rBlur)) + $rOrig;
                    if ($rNew > 255) {
                        $rNew = 255;
                    } elseif ($rNew < 0) {
                        $rNew = 0;
                    }
                    $gNew = ($amount * ($gOrig - $gBlur)) + $gOrig;
                    if ($gNew > 255) {
                        $gNew = 255;
                    } elseif ($gNew < 0) {
                        $gNew = 0;
                    }
                    $bNew = ($amount * ($bOrig - $bBlur)) + $bOrig;
                    if ($bNew > 255) {
                        $bNew = 255;
                    } elseif ($bNew < 0) {
                        $bNew = 0;
                    }
                    $rgbNew = ($rNew << 16) + ($gNew << 8) + $bNew;
                    ImageSetPixel($img, $x, $y, $rgbNew);
                }
            }
        }
        imagedestroy($imgCanvas);
        imagedestroy($imgBlur);
        $this->image = $img;
    }

    /*

'filter'    => array('negative', 'gray', 'bright', 'contrast', 'colorize', 'edgedetect', 'emboss', 'gaussianblur', 'selectiveblur', 'sketch', 'smooth', 'pixelate')

IMG_FILTER_NEGATE: Инвертирует все цвета изображения.
IMG_FILTER_GRAYSCALE: Преобразует цвета изображения в градации серого.
IMG_FILTER_BRIGHTNESS: Изменяет яркость изображения. Используйте аргумент arg1 для задания уровня яркости.
IMG_FILTER_CONTRAST: Изменяет контрастность изображения. Используйте аргумент arg1 для задания уровня контрастности.
IMG_FILTER_COLORIZE: То же, что и IMG_FILTER_GRAYSCALE, за исключением того, что можно задать цвет. Используйте аргументы arg1, arg2 и arg3 для указания каналов red, green, blue, а arg4 для alpha канала. Диапазон для каждого канала цвета от 0 до 255.
IMG_FILTER_EDGEDETECT: Использует определение границ для их подсветки.
IMG_FILTER_EMBOSS: Добавляет рельеф.
IMG_FILTER_GAUSSIAN_BLUR: Размывает изображение по методу Гауса.
IMG_FILTER_SELECTIVE_BLUR: Размывает изображение.
IMG_FILTER_MEAN_REMOVAL: Использует усреднение для достижения эффекта "эскиза".
IMG_FILTER_SMOOTH: Делает границы более плавными, а изображение менее четким. Используйте аргумент arg1 для задания уровня гладкости.
IMG_FILTER_PIXELATE: Применяет эффект пикселирования. Используйте аргумент arg1 для задания размера блока и аргумент arg2 для задания режима эффекта пикселирования.

arg1
IMG_FILTER_BRIGHTNESS: Уровень яркости.
IMG_FILTER_CONTRAST: Уровень контрастности.
IMG_FILTER_COLORIZE: Значение красного компонента цвета.
IMG_FILTER_SMOOTH: Уровень сглаживания.
IMG_FILTER_PIXELATE: Размер блока в пикселах.

arg2
IMG_FILTER_COLORIZE: Значение зеленого компонента цвета.
IMG_FILTER_PIXELATE: Использовать усовершенствованный эффект пикселирования или нет (по умолчанию FALSE).

arg3
IMG_FILTER_COLORIZE: Значение синего компонента цвета.

arg4
IMG_FILTER_COLORIZE: Альфа канал, значение между 0 и 127. 0 означает непрозрачность, 127 соответствует абсолютной прозрачности.
*/
    private function filter($params)
    {
        foreach ($params as $p => $param) {
            switch (ImageRendererUtils::$allowedGraphicTransformations['f'][$p]) {
                case 'negative':
                    imagefilter($this->image, IMG_FILTER_NEGATE);
                    break;
                case 'grayscale':
                    imagefilter($this->image, IMG_FILTER_GRAYSCALE);
                    break;
                case 'edgedetect':
                    imagefilter($this->image, IMG_FILTER_EDGEDETECT);
                    break;
                case 'emboss':
                    imagefilter($this->image, IMG_FILTER_EMBOSS);
                    break;
                case 'gaussianblur':
                    imagefilter($this->image, IMG_FILTER_GAUSSIAN_BLUR);
                    break;
                case 'selectiveblur':
                    imagefilter($this->image, IMG_FILTER_SELECTIVE_BLUR);
                    break;
                case 'sketch':
                    imagefilter($this->image, IMG_FILTER_MEAN_REMOVAL);
                    break;
                case 'smooth':
                    imagefilter($this->image, IMG_FILTER_SMOOTH, $param[0]);
                    break;
                case 'brightness':
                    imagefilter($this->image, IMG_FILTER_BRIGHTNESS, $param[0]);
                    break;
                case 'contrast':
                    imagefilter($this->image, IMG_FILTER_CONTRAST, $param[0]);
                    break;
                case 'pixelate':
                    imagefilter($this->image, IMG_FILTER_PIXELATE, $param[0], $param[1]);
                    break;
                case 'colorize':
                    imagefilter($this->image, IMG_FILTER_COLORIZE, $param[0], $param[1], $param[2], $param[3]);
                    break;
            }
        }
    }

    private function watermark($data)
    {


        $params = array(
            $this->image,
            array('width' => imagesx($this->image), 'height' => imagesy($this->image)),
            $this->data['docRoot'] . $_SESSION['imagecachedata'][$this->imgHash]['w']['i'],

            $_SESSION['imagecachedata'][$this->imgHash]['w']['t'] ? $_SESSION['imagecachedata'][$this->imgHash]['w']['t'] : 'PAVE',
            $_SESSION['imagecachedata'][$this->imgHash]['w']['p']
        );


        if (file_exists($params[2])) {
            $tmp = imagecreatefrompng($params[2]);
            $size = GetImageSize($params[2]);
            $img = imagecreatetruecolor($params[1]['width'], $params[1]['height']);
            imagesavealpha($img, true);
            $trans_colour = imagecolorallocatealpha($img, 0, 0, 0, 127);
            imagefill($img, 0, 0, $trans_colour);
            imagecopyresampled($img, $params[0], 0, 0, 0, 0, $params[1]['width'], $params[1]['height'], $params[1]['width'], $params[1]['height']);
            imagecopy($img, $params[0], 0, 0, 0, 0, $params[1]['width'], $params[1]['height']);

            switch ($params[3]) {
                case 'PAVE':
                    $posx = 0;
                    $posy = 0;
                    while ($posy < $params[1]['height']) {
                        while ($posx < $params[1]['width']) {
                            imagecopy($img, $tmp, $posx, $posy, 0, 0, $size[0], $size[1]);
                            $posx += $size[0];
                        }
                        $posx = 0;
                        $posy += $size[1];
                    }
                    break;
                case 'INSERT':
                    $pos = explode('-', $params[4]);
                    switch ($pos[0]) {
                        case 'LEFT_BOTTOM':
                            $pos[2] = $params[1]['height'] - $size[1] - $pos[2];
                            break;

                        case 'RIGHT_BOTTOM':
                            $pos[2] = $params[1]['height'] - $size[1] - $pos[2];
                            $pos[1] = $params[1]['width'] - $size[0] - $pos[1];
                            break;

                        case 'RIGHT_TOP':
                            $pos[1] = $params[1]['width'] - $size[0] - $pos[1];
                            break;

                        case 'CENTER_BOTTOM':
                            $pos[2] = $params[1]['height'] - $size[1] - $pos[1];
                            $pos[1] = ($params[1]['width'] - $size[0]) / 2;
                            break;

                        case 'CENTER_TOP':
                            $pos[2] = $pos[1];
                            $pos[1] = ($params[1]['width'] - $size[0]) / 2;
                            break;

                        case 'CENTER_LEFT':
                            $pos[2] = ($params[1]['height'] - $size[1]) / 2;
                            break;

                        case 'CENTER_RIGHT':
                            $pos[2] = ($params[1]['height'] - $size[1]) / 2;
                            $pos[1] = $params[1]['width'] - $size[0] - $pos[1];
                            break;

                        case 'ABSOLUTE_CENTER':
                            $pos[2] = ($params[1]['height'] - $size[1]) / 2;
                            $pos[1] = ($params[1]['width'] - $size[0]) / 2;
                            break;

                        case 'LEFT_TOP':
                        default:
                            $pos[1] = $pos[1] ? $pos[1] : 0;
                            $pos[2] = $pos[2] ? $pos[2] : 0;
                            break;
                    }
                    imagecopy($img, $tmp, $pos[1], $pos[2], 0, 0, $size[0], $size[1]);
                    break;
            }
            imagedestroy($tmp);
            $this->image = $img;
        }
    }

    public function generateCacheFileName()
    {
        if ($_GET['imghash']) {
            $this->cacheFileName = preg_replace("/[^a-zA-Z0-9]/", "", $_GET['imghash']);
        } else {
            $this->cacheFileName = ImageRendererUtils::arrayToImageName($this->settings, $this->path);
            if (file_exists(CACHE_DIR . $this->cacheFileName)) {
                $data = file_get_contents(CACHE_DIR . $this->cacheFileName);
                $path_info = pathinfo(CACHE_DIR . $this->cacheFileName);
                switch (strtolower($path_info['extension'])) {
                    case "png"  :
                        $mime = 'image/png';
                        break;
                    case "gif"  :
                        $mime = 'image/gif';
                        break;
                    case "jpg"  :
                    case "jpeg" :
                    default     :
                        $mime = 'image/jpeg';
                        break;
                }
                unset($_SESSION['imagecachedata'][$_GET['imghash']]);
                header("Content-type: $mime");
                header('Content-Length: ' . strlen($data));
                echo $data;
                die();
            }
        }
    }

}


class ImageRendererUtils
{

    public static $allowedGraphicTransformations = array( // набор допустимых трансформаций
        'q' /* quality   */ => array('v' => 'value'),
        'r' /* resize    */ => array('w' => 'width', 'h' => 'height', 'r' => 'cratio', 'a' => 'calign'),
        'w' /* watermark */ => array('i' => 'wpicture', 't' => 'wtype', 'p' => 'wposition'),
        's' /* sharping  */ => array('a' => 'amount', 'r' => 'radius', 't' => 'treshold'),
        'f' /* filter    */ => array('n' => 'negative', 'g' => 'grayscale', 'b' => 'brightness', 'c' => 'contrast',
            'l' => 'colorize', 'j' => 'edgedetect', 'e' => 'emboss', 'q' => 'gaussianblur',
            'r' => 'selectiveblur', 's' => 'sketch', 'u' => 'smooth', 'p' => 'pixelate')
    );

    public static $allowedGraphicMethods = array(
        'q' => 'quality',
        'r' => 'resize',
        'w' => 'watermark',
        's' => 'sharping',
        'f' => 'filter'
    );


    /**
     * @param $settings
     * @param $file
     * @return mixed
     */
    static function arrayToImageName($settings, $file)
    {
        $str = '';
        foreach ($settings as $s => $setting) {
            $part[0] = $s;
            foreach ($setting as $p => $param) {
                if (!isset(self::$allowedGraphicTransformations[$s][$p]) || !method_exists('ImageRenderer', self::$allowedGraphicMethods[$s])) {
                    unset($setting[$p]);
                }
                $part[1] = $p;
                if (is_array($param)) {
                    $part[2] = implode('-', $param);
                } else {
                    $part[2] = $param;
                }
                $str .= $part[0] . $part[1] . $part[2] . "--";
            }
            if (empty($setting)) unset($settings[$s]);
        }
        return str_replace(
            array('!', ';', ':', '^', '?', '&', '*', '(', ')', '|', '{', '}', '[', ']', '"', "'", '/', '\\'),
            array('', '', '.', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''),
            ($str ? $str : '--') . 'file' . str_replace('/', '-', str_replace('-', '~', $file)));
    }


    function dropError($status, $text)
    {
        header($status);
        echo $text;
        unset($_SESSION['imagecachedata'][$_GET['imghash']]);
        exit();
    }


}