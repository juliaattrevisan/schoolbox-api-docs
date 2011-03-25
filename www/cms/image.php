<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2005 Lektec Consulting         *
*                                                           *
************************************************************/

/* This file is used to dynamically resize an image file.  *
 * It will be used to fill the image bank with appropriate *
 * size images for display.				   */

require_once("../include/include.php");
require_once("../include/session.php");
require_once("../include/permission.php");

define("MAX_IMAGE_SIZE", 60000000);

if($_GET['id'] && $_GET['width'] && $_GET['height'])
{
	$db = new dbManager;
	
	//get file information
    if (is_numeric($_GET['myFile'])) {
        $query = "SELECT id, owner, mime_type, deleted FROM myFile WHERE id = {$_GET['id']}";
        $fileDir = Cfg::get('myfile_dir');
    }
    else {
        $query = "SELECT id, mime_type, category, deleted FROM file WHERE id = {$_GET['id']}";
        $fileDir = Cfg::get('file_dir');
    }
    
	$imageInfo = $db->getRow($query);

    /* Check permissions on the file to see who can view it. Images can be made
     * in two contexts - eportfolio or generic files
     */
    if (is_numeric($_GET['myFile'])) {
        require_once($_SERVER['DOCUMENT_ROOT'].'/../library/eportfolio.php');
        require_once($_SERVER['DOCUMENT_ROOT'].'/../library/group.php');

        $eportfolioHandler = new ePortfolio($imageInfo['owner']);

        if (!$eportfolioHandler->canEdit($imageInfo['owner'])) {
            error(5);
        }
    }
    else {
        perm($imageInfo['category'], HOMEPAGESERVICE);
    }

	//is image exists, the user has permission and its not deleted then output the image
	if(isset($imageInfo['id']) && !$imageInfo['deleted'])
	{		
		if(isset($_GET['resample']) && isset($_GET['quality']))	
			image_resize($fileDir."/".$imageInfo['id'].".dat", $_GET['width'], $_GET['height'], $_GET['resample'], $_GET['quality']);
		else if(isset($_GET['resample']))
			image_resize($fileDir."/".$imageInfo['id'].".dat", $_GET['width'], $_GET['height'], $_GET['resample']);
		else if($_GET['quality'])
		{
			if($_GET['quality'] < 50 )//if low quality dont bother resampling
				image_resize($fileDir."/".$imageInfo['id'].".dat", $_GET['width'], $_GET['height'], 0, $_GET['quality']);
			else
				image_resize($fileDir."/".$imageInfo['id'].".dat", $_GET['width'], $_GET['height'], 1, $_GET['quality']);
	
		}
		else	
			image_resize($fileDir."/".$imageInfo['id'].".dat", $_GET['width'], $_GET['height']);
	}
}

function image_resize($src, $maxWidth,$maxHeight,$resample=1, $quality=75)
{ 
	//first check if we have a cache of this file to avoid resize process time
	//note that we ignore other values like resample and quality when retrieving cache images
	//we assume these things will mainly stay the same (cache will need to be rebuilt if they change)

	$filename = Cfg::get('thumbnail_dir')."/".$_GET['id']."-".$maxHeight."x".$maxWidth.".jpg";
	if(file_exists($filename))
	{
		
		outputImageFile($filename);
		exit;
	}


	if (file_exists($src) )
	{ 
		// image src size 
		$srcSize  = getImageSize($src); 
		
		//test image size (cannot handle large 30mb+ uncompressed images)
		if(($srcSize[0] * $srcSize[1]) > MAX_IMAGE_SIZE) 
		{
			outputImageFile($_SERVER['DOCUMENT_ROOT']."/images/cross.gif", 1);
			exit;
		}

		// dont enlarge images beyond existing size (doesnt look good)
		if($srcSize[0] < $maxHeight && $srcSize[1] < $maxWidth)
		{
			outputImageFile($src, $srcSize[2]);
			exit;
			//$destSize[1] = $srcSize[1];
			//$destSize[0] = $srcSize[0];
		}
		else
		{
			//resize image propotionally within the max height and width
			// image dest size $destSize[0] = width, $destSize[1] = height 
			$srcRatio  = $srcSize[0]/$srcSize[1]; // width/height ratio 
			$destRatio = $maxWidth/$maxHeight; 
			if ($destRatio > $srcRatio) 
			{ 
				$destSize[1] = $maxHeight; 
				$destSize[0] = $maxHeight*$srcRatio; 
			} 
			else 
			{ 
				$destSize[0] = $maxWidth; 
				$destSize[1] = $maxWidth/$srcRatio; 
			}
		} 

		// true color image, with anti-aliasing 
		$destImage = imageCreateTrueColor($destSize[0],$destSize[1]); 

		// src image 
		switch ($srcSize[2]) 
		{ 
			case 1: //GIF 
				if(is_animated($src)) { //leave animateds alone we cant handle them so dont mess them up  {
					outputImageFile($src, $srcSize[2]);
					exit;	
				}
				else {
					//resize gifs and handle that transparent background
					$srcImage = imageCreateFromGif($src);
					$originaltransparentcolor = imagecolortransparent( $srcImage );
					
					if( $originaltransparentcolor >= 0 && $originaltransparentcolor < imagecolorstotal( $srcImage ) ) {
						$transparentcolor = imagecolorsforindex( $srcImage, $originaltransparentcolor );
						$newtransparentcolor = imagecolorallocate( 	$destImage,
																	$transparentcolor['red'],
																	$transparentcolor['green'],
																	$transparentcolor['blue']
						);
						// for true color image, we must fill the background manually
						imagefill( $destImage, 0, 0, $newtransparentcolor );
						imagecolortransparent( $destImage, $newtransparentcolor );
					}
				}
				
			break;

			case 2: //JPEG 
				$srcImage = imageCreateFromJpeg($src); 
			break; 

			case 3: //PNG 
				$srcImage = imageCreateFromPng($src); 
			break; 

			default: 
				return false; 
			break; 
	       } 

		// resampling 
		if($resample)
			imageCopyResampled($destImage, $srcImage, 0, 0, 0, 0,$destSize[0]+1,$destSize[1]+1,$srcSize[0],$srcSize[1]); 
		else
			imageCopyResized($destImage, $srcImage, 0, 0, 0, 0,$destSize[0]+1,$destSize[1]+1,$srcSize[0],$srcSize[1]); 


		cacheImage($destImage, $filename, $srcSize[2], $quality);
		outputImage($destImage, $srcSize[2], $quality); 
	} 
	else 
	{ 
		return false; 
	} 
}

function cacheImage($image, $filename, $type=2, $quality=75)
{
	if($type == 1) 
		@imagegif($image, $filename);
	else if($type == 3)
		@imagepng($image, $filename);
	else
		@imagejpeg($image, $filename, $quality); 
}


function outputImage($image, $type=2, $quality=75)
{
	if($type == 1) {
		header("Content-Type: image/gif");
		imagegif($image);
	}
	else if($type == 3) {
		header("Content-Type: image/png");
		imagepng($image);
	}
	else {
		header("Content-Type: image/jpg");
		imagejpeg($image, '',$quality); 
	}
}

function outputImageFile($filename)
{
	header("Content-Type: image/jpg");
	readfile($filename);
}

function is_animated($filename) {
	//function works out if file is animated gif
	
	$filecontents=file_get_contents($filename);
	$str_loc=0;
	$count=0;
	
	while ($count < 2) { # There is no point in continuing after we find a 2nd frame
		$where1=strpos($filecontents,"\x00\x21\xF9\x04",$str_loc);
		if ($where1 === FALSE) {
			break;
		}
		else {
			$str_loc=$where1+1;
			$where2=strpos($filecontents,"\x00\x2C",$str_loc);
			if ($where2 === FALSE) {
				break;
			}
			else {
				if ($where1+8 == $where2) {
						$count++;
				}
				$str_loc=$where2+1;
			}
		}
	}
	
	if ($count > 1)
			return true;
	else
			return false;
}



?>
