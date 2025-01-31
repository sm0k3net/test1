<?php

$PATH_TO_CLASS = dirname(ereg_replace("\\\\", "/", __FILE__)) . "/" . "ConvertTables" . "/";
define ("CONVERT_TABLES_DIR", $PATH_TO_CLASS);
define ("DEBUG_MODE", 1);

class ConvertCharset{
     var $RecognizedEncoding; // (boolean) This value keeps information if string contains multibyte chars.
     var $Entities; // (boolean) This value keeps information if output should be with numeric entities.
     var $FromCharset; // (string) This value keeps information about source (from) encoding
     var $ToCharset; // (string) This value keeps information about destination (to) encoding
     var $CharsetTable; // (array) This property keeps convert Table inside


     function w1251()
     {
      return array('0000'=>'00',
                '0001'=>'01',
                '0002'=>'02',
                '0003'=>'03',
                '0004'=>'04',
                '0005'=>'05',
                '0006'=>'06',
                '0007'=>'07',
                '0008'=>'08',
                '0009'=>'09',
                '000A'=>'0A',
                '000B'=>'0B',
                '000C'=>'0C',
                '000D'=>'0D',
                '000E'=>'0E',
                '000F'=>'0F',
                '0010'=>'10',
                '0011'=>'11',
                '0012'=>'12',
                '0013'=>'13',
                '0014'=>'14',
                '0015'=>'15',
                '0016'=>'16',
                '0017'=>'17',
                '0018'=>'18',
                '0019'=>'19',
                '001A'=>'1A',
                '001B'=>'1B',
                '001C'=>'1C',
                '001D'=>'1D',
                '001E'=>'1E',
                '001F'=>'1F',
                '0020'=>'20',
                '0021'=>'21',
                '0022'=>'22',
                '0023'=>'23',
                '0024'=>'24',
                '0025'=>'25',
                '0026'=>'26',
                '0027'=>'27',
                '0028'=>'28',
                '0029'=>'29',
                '002A'=>'2A',
                '002B'=>'2B',
                '002C'=>'2C',
                '002D'=>'2D',
                '002E'=>'2E',
                '002F'=>'2F',
                '0030'=>'30',
                '0031'=>'31',
                '0032'=>'32',
                '0033'=>'33',
                '0034'=>'34',
                '0035'=>'35',
                '0036'=>'36',
                '0037'=>'37',
                '0038'=>'38',
                '0039'=>'39',
                '003A'=>'3A',
                '003B'=>'3B',
                '003C'=>'3C',
                '003D'=>'3D',
                '003E'=>'3E',
                '003F'=>'3F',
                '0040'=>'40',
                '0041'=>'41',
                '0042'=>'42',
                '0043'=>'43',
                '0044'=>'44',
                '0045'=>'45',
                '0046'=>'46',
                '0047'=>'47',
                '0048'=>'48',
                '0049'=>'49',
                '004A'=>'4A',
                '004B'=>'4B',
                '004C'=>'4C',
                '004D'=>'4D',
                '004E'=>'4E',
                '004F'=>'4F',
                '0050'=>'50',
                '0051'=>'51',
                '0052'=>'52',
                '0053'=>'53',
                '0054'=>'54',
                '0055'=>'55',
                '0056'=>'56',
                '0057'=>'57',
                '0058'=>'58',
                '0059'=>'59',
                '005A'=>'5A',
                '005B'=>'5B',
                '005C'=>'5C',
                '005D'=>'5D',
                '005E'=>'5E',
                '005F'=>'5F',
                '0060'=>'60',
                '0061'=>'61',
                '0062'=>'62',
                '0063'=>'63',
                '0064'=>'64',
                '0065'=>'65',
                '0066'=>'66',
                '0067'=>'67',
                '0068'=>'68',
                '0069'=>'69',
                '006A'=>'6A',
                '006B'=>'6B',
                '006C'=>'6C',
                '006D'=>'6D',
                '006E'=>'6E',
                '006F'=>'6F',
                '0070'=>'70',
                '0071'=>'71',
                '0072'=>'72',
                '0073'=>'73',
                '0074'=>'74',
                '0075'=>'75',
                '0076'=>'76',
                '0077'=>'77',
                '0078'=>'78',
                '0079'=>'79',
                '007A'=>'7A',
                '007B'=>'7B',
                '007C'=>'7C',
                '007D'=>'7D',
                '007E'=>'7E',
                '007F'=>'7F',
                '0402'=>'80',
                '0403'=>'81',
                '201A'=>'82',
                '0453'=>'83',
                '201E'=>'84',
                '2026'=>'85',
                '2020'=>'86',
                '2021'=>'87',
                '20AC'=>'88',
                '2030'=>'89',
                '0409'=>'8A',
                '2039'=>'8B',
                '040A'=>'8C',
                '040C'=>'8D',
                '040B'=>'8E',
                '040F'=>'8F',
                '0452'=>'90',
                '2018'=>'91',
                '2019'=>'92',
                '201C'=>'93',
                '201D'=>'94',
                '2022'=>'95',
                '2013'=>'96',
                '2014'=>'97',
                '2122'=>'99',
                '0459'=>'9A',
                '203A'=>'9B',
                '045A'=>'9C',
                '045C'=>'9D',
                '045B'=>'9E',
                '045F'=>'9F',
                '00A0'=>'A0',
                '040E'=>'A1',
                '045E'=>'A2',
                '0408'=>'A3',
                '00A4'=>'A4',
                '0490'=>'A5',
                '00A6'=>'A6',
                '00A7'=>'A7',
                '0401'=>'A8',
                '00A9'=>'A9',
                '0404'=>'AA',
                '00AB'=>'AB',
                '00AC'=>'AC',
                '00AD'=>'AD',
                '00AE'=>'AE',
                '0407'=>'AF',
                '00B0'=>'B0',
                '00B1'=>'B1',
                '0406'=>'B2',
                '0456'=>'B3',
                '0491'=>'B4',
                '00B5'=>'B5',
                '00B6'=>'B6',
                '00B7'=>'B7',
                '0451'=>'B8',
                '2116'=>'B9',
                '0454'=>'BA',
                '00BB'=>'BB',
                '0458'=>'BC',
                '0405'=>'BD',
                '0455'=>'BE',
                '0457'=>'BF',
                '0410'=>'C0',
                '0411'=>'C1',
                '0412'=>'C2',
                '0413'=>'C3',
                '0414'=>'C4',
                '0415'=>'C5',
                '0416'=>'C6',
                '0417'=>'C7',
                '0418'=>'C8',
                '0419'=>'C9',
                '041A'=>'CA',
                '041B'=>'CB',
                '041C'=>'CC',
                '041D'=>'CD',
                '041E'=>'CE',
                '041F'=>'CF',
                '0420'=>'D0',
                '0421'=>'D1',
                '0422'=>'D2',
                '0423'=>'D3',
                '0424'=>'D4',
                '0425'=>'D5',
                '0426'=>'D6',
                '0427'=>'D7',
                '0428'=>'D8',
                '0429'=>'D9',
                '042A'=>'DA',
                '042B'=>'DB',
                '042C'=>'DC',
                '042D'=>'DD',
                '042E'=>'DE',
                '042F'=>'DF',
                '0430'=>'E0',
                '0431'=>'E1',
                '0432'=>'E2',
                '0433'=>'E3',
                '0434'=>'E4',
                '0435'=>'E5',
                '0436'=>'E6',
                '0437'=>'E7',
                '0438'=>'E8',
                '0439'=>'E9',
                '043A'=>'EA',
                '043B'=>'EB',
                '043C'=>'EC',
                '043D'=>'ED',
                '043E'=>'EE',
                '043F'=>'EF',
                '0440'=>'F0',
                '0441'=>'F1',
                '0442'=>'F2',
                '0443'=>'F3',
                '0444'=>'F4',
                '0445'=>'F5',
                '0446'=>'F6',
                '0447'=>'F7',
                '0448'=>'F8',
                '0449'=>'F9',
                '044A'=>'FA',
                '044B'=>'FB',
                '044C'=>'FC',
                '044D'=>'FD',
                '044E'=>'FE',
                '044F'=>'FF');

     }

     function ConvertCharset ($FromCharset, $ToCharset, $TurnOnEntities = false)
    {

         $this -> FromCharset = strtolower($FromCharset);
         $this -> ToCharset = strtolower($ToCharset);
         $this -> Entities = $TurnOnEntities;
      
         $this -> CharsetTable=array('windows-1251'=>ConvertCharset::w1251());
         $this -> Flip=array_flip(ConvertCharset::w1251());
         
       
         
             
                 
         }


     function UnicodeEntity ($UnicodeString)
    {
         $OutString = "";
         $StringLenght = strlen ($UnicodeString);
         for ($CharPosition = 0; $CharPosition < $StringLenght; $CharPosition++)
        {
             $Char = $UnicodeString [$CharPosition];
             $AsciiChar = ord ($Char);

             if ($AsciiChar < 128){
                 $OutString .= $Char;
                 }
            else if ($AsciiChar >> 5 == 6){
                 $FirstByte = ($AsciiChar & 31);
                 $CharPosition++;
                 $Char = $UnicodeString [$CharPosition];
                 $AsciiChar = ord ($Char);
                 $SecondByte = ($AsciiChar & 63);
                 $AsciiChar = ($FirstByte * 64) + $SecondByte;
                 $Entity = sprintf ("&#%d;", $AsciiChar);
                 $OutString .= $Entity;
                 }
            else if ($AsciiChar >> 4 == 14){
                 $FirstByte = ($AsciiChar & 31);
                 $CharPosition++;
                 $Char = $UnicodeString [$CharPosition];
                 $AsciiChar = ord ($Char);
                 $SecondByte = ($AsciiChar & 63);
                 $CharPosition++;
                 $Char = $UnicodeString [$CharPosition];
                 $AsciiChar = ord ($Char);
                 $ThidrByte = ($AsciiChar & 63);
                 $AsciiChar = ((($FirstByte * 64) + $SecondByte) * 64) + $ThidrByte;

                 $Entity = sprintf ("&#%d;", $AsciiChar);
                 $OutString .= $Entity;
                 }
            else if ($AsciiChar >> 3 == 30){
                 $FirstByte = ($AsciiChar & 31);
                 $CharPosition++;
                 $Char = $UnicodeString [$CharPosition];
                 $AsciiChar = ord ($Char);
                 $SecondByte = ($AsciiChar & 63);
                 $CharPosition++;
                 $Char = $UnicodeString [$CharPosition];
                 $AsciiChar = ord ($Char);
                 $ThidrByte = ($AsciiChar & 63);
                 $CharPosition++;
                 $Char = $UnicodeString [$CharPosition];
                 $AsciiChar = ord ($Char);
                 $FourthByte = ($AsciiChar & 63);
                 $AsciiChar = ((((($FirstByte * 64) + $SecondByte) * 64) + $ThidrByte) * 64) + $FourthByte;

                 $Entity = sprintf ("&#%d;", $AsciiChar);
                 $OutString .= $Entity;
                 }
             }
         return $OutString;
         }


     function HexToUtf ($UtfCharInHex)
    {
         $OutputChar = "";
         $UtfCharInDec = hexdec($UtfCharInHex);
         if($UtfCharInDec < 128) $OutputChar .= chr($UtfCharInDec);
         else if($UtfCharInDec < 2048)$OutputChar .= chr(($UtfCharInDec >> 6) + 192) . chr(($UtfCharInDec & 63) + 128);
         else if($UtfCharInDec < 65536)$OutputChar .= chr(($UtfCharInDec >> 12) + 224) . chr((($UtfCharInDec >> 6) & 63) + 128) . chr(($UtfCharInDec & 63) + 128);
         else if($UtfCharInDec < 2097152)$OutputChar .= chr($UtfCharInDec >> 18 + 240) . chr((($UtfCharInDec >> 12) & 63) + 128) . chr(($UtfCharInDec >> 6) & 63 + 128) . chr($UtfCharInDec & 63 + 128);
         return $OutputChar;
         }



     function Convert ($StringToChange)
    {
         if(!strlen($StringToChange)) return '';
         $StringToChange = (string)($StringToChange);

         if($this -> FromCharset == $this -> ToCharset) return $StringToChange;

         $NewString = "";


         if ($this -> FromCharset != "utf-8")
        {
            $strl=strlen($StringToChange);
             for ($i = 0; $i < $strl; $i++)
            {
                 $HexChar = "";
                 $UnicodeHexChar = "";
                 $HexChar = strtoupper(dechex(ord($StringToChange[$i])));
                 if (strlen($HexChar) == 1) $HexChar = "0" . $HexChar;
                
              
                 if ($this -> ToCharset != "utf-8")
                {
                     if (in_array($HexChar, $this -> CharsetTable[$this -> FromCharset]))
                        {
                         $UnicodeHexChar = array_search($HexChar, $this -> CharsetTable[$this -> FromCharset]);
                         $UnicodeHexChars = explode("+", $UnicodeHexChar);
                         for($UnicodeHexCharElement = 0; $UnicodeHexCharElement < count($UnicodeHexChars); $UnicodeHexCharElement++)
                        {
                             if (array_key_exists($UnicodeHexChars[$UnicodeHexCharElement], $this -> CharsetTable[$this -> ToCharset]))
                                {
                                 if ($this -> Entities == true)
                                {
                                     $NewString .= $this -> UnicodeEntity($this -> HexToUtf($UnicodeHexChars[$UnicodeHexCharElement]));
                                     }
                                else
                                    {
                                     $NewString .= chr(hexdec($this -> CharsetTable[$this -> ToCharset][$UnicodeHexChars[$UnicodeHexCharElement]]));
                                     }
                                 }
                            else
                                {
                                 print $this -> DebugOutput(0, 1, $StringToChange[$i]);
                                 }
                             } //for($UnicodeH...
                         }
                    else
                        {
                         print $this -> DebugOutput(0, 2, $StringToChange[$i]);
                         }
                     }
                else
                    {
                     if (in_array("$HexChar", $this -> CharsetTable[$this -> FromCharset]))
                        {
                         $UnicodeHexChar = $this -> Flip[$HexChar];                                
                         $UnicodeHexChars = explode("+", $UnicodeHexChar);
                         for($UnicodeHexCharElement = 0; $UnicodeHexCharElement < count($UnicodeHexChars); $UnicodeHexCharElement++)
                        {
                         //    if ($this -> Entities == true)
                          //  {
                           //      $NewString .= $this -> UnicodeEntity($this -> HexToUtf($UnicodeHexChars[$UnicodeHexCharElement]));
                            //     }
                           // else
                            //    {
       //                          $NewString .= $this -> HexToUtf($UnicodeHexChars[$UnicodeHexCharElement]);
                                                                  
                             $OutputChar = "";
                             $UtfCharInDec = hexdec($UnicodeHexChars[$UnicodeHexCharElement]);
                             if($UtfCharInDec < 128) $OutputChar .= chr($UtfCharInDec);
                             else if($UtfCharInDec < 2048)$OutputChar .= chr(($UtfCharInDec >> 6) + 192) . chr(($UtfCharInDec & 63) + 128);
                             else if($UtfCharInDec < 65536)$OutputChar .= chr(($UtfCharInDec >> 12) + 224) . chr((($UtfCharInDec >> 6) & 63) + 128) . chr(($UtfCharInDec & 63) + 128);
                             else if($UtfCharInDec < 2097152)$OutputChar .= chr($UtfCharInDec >> 18 + 240) . chr((($UtfCharInDec >> 12) & 63) + 128) . chr(($UtfCharInDec >> 6) & 63 + 128) . chr($UtfCharInDec & 63 + 128);
                             $NewString .=$OutputChar;
                            //     }
                             } // for
                         }
                    else
                        {
                         print $this -> DebugOutput(0, 2, $StringToChange[$i]);
                         }
                     }
                 }
             }

         else if($this -> FromCharset == "utf-8")
        {
             $HexChar = "";
             $UnicodeHexChar = "";
             foreach ($this -> CharsetTable[$this -> ToCharset] as $UnicodeHexChar => $HexChar)
            {
                 if ($this -> Entities == true){
                     $EntitieOrChar = $this -> UnicodeEntity($this -> HexToUtf($UnicodeHexChar));
                     }
                else
                    {
                     $EntitieOrChar = chr(hexdec($HexChar));
                     }
                 $StringToChange = str_replace($this -> HexToUtf($UnicodeHexChar), $EntitieOrChar, $StringToChange);
                 }
             $NewString = $StringToChange;
             }

         return $NewString;
         }


     function ConvertArray(& $array)
    {
         if (!is_array($array))
            {
             $array = $this -> Convert($array);
             return;
             }
         while(list($k, $v) = each($array))
        {
             $this -> ConvertArray($v);
             $array[$k] = $v;
             }
         }


     function DebugOutput ($Group, $Number, $Value = false)
    {
         $Debug[0][0] = "Error, can NOT read file: " . $Value . "<br>";
         $Debug[0][1] = "Error, can't find maching char \"" . $Value . "\" in destination encoding table!" . "<br>";
         $Debug[0][2] = "Error, can't find maching char \"" . $Value . "\" in source encoding table!" . "<br>";
         $Debug[0][3] = "Error, you did NOT set variable " . $Value . " in Convert() function." . "<br>";
         $Debug[0][4] = "You can NOT convert string from " . $Value . " to " . $Value . "!" . "<BR>";
         $Debug[1][0] = "Notice, you are trying to convert string from " . $Value . " to " . $Value . ", don't you feel it's strange? ;-)" . "<br>";
         $Debug[1][1] = "Notice, both charsets " . $Value . " are identical! Check encoding tables files." . "<br>";
         $Debug[1][2] = "Notice, there is no unicode char in the string you are trying to convert." . "<br>";

         if (DEBUG_MODE >= $Group)
        {
             return $Debug[$Group][$Number];
             }
         } // function DebugOutput

    } //class ends here
?>
