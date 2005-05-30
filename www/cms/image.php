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

define("MAX_IMAGE_SIZE", 6000000);

if($_GET['id'] && $_GET['width'] && $_GET['height'])
{
	$db = new dbManager;
	
	//get file information	
	$query = "SELECT id, mime_type, category, deleted FROM file WHERE id = {$_GET['id']}";
	$imageInfo = $db->dbGetSingleRow($query);

	perm($imageInfo['category'], HOMEPAGESERVICE);
	//is image exists, the user has permission and its not deleted then output the image
	if(isset($imageInfo['id']) && !$imageInfo['deleted'])
	{		
		if(isset($_GET['resample']) && isset($_GET['quality']))	
			image_resize(FILE_directory."/".$imageInfo['id'].".dat", $_GET['width'], $_GET['height'], $_GET['resample'], $_GET['quality']);
		else if(isset($_GET['resample']))
			image_resize(FILE_directory."/".$imageInfo['id'].".dat", $_GET['width'], $_GET['height'], $_GET['resample']);
		else if($_GET['quality'])
		{
			if($_GET['quality'] < 50 )//if low quality dont bother resampling
				image_resize(FILE_directory."/".$imageInfo['id'].".dat", $_GET['width'], $_GET['height'], 0, $_GET['quality']);
			else
				image_resize(FILE_directory."/".$imageInfo['id'].".dat", $_GET['width'], $_GET['height'], 1, $_GET['quality']);
	
		}
		else	
			image_resize(FILE_directory."/".$imageInfo['id'].".dat", $_GET['width'], $_GET['height']);
	}
}

function image_resize($src, $maxWidth,$maxHeight,$resample=1, $quality=75)
{ 
	//first check if we have a cache of this file to avoid resize process time
	//note that we ignore other values like resample and quality when retrieving cache images
	//we assume these things will mainly stay the same (cache will need to be rebuilt if they change)

	$filename = THUMBNAIL_IMAGE_CACHE."/".$_GET['id']."-".$maxHeight."x".$maxWidth.".jpg";
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
			outputImageFile($_SERVER['DOCUMENT_ROOT']."/newimages/cross.gif");
			exit;
		}

		// dont enlarge images beyond existing size (doesnt look good)
		if($srcSize[0] < $maxHeight && $srcSize[1] < $maxWidth)
		{
			outputImageFile($src);
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
				$srcImage = imageCreateFromGif($src); 
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


		cacheImage($destImage, $filename, $quality);
		outputImage($destImage, $quality); 
	} 
	else 
	{ 
		return false; 
	} 
}

function cacheImage($image, $filename, $quality)
{
	@imageJpeg($image, $filename, $quality); 
}


function outputImage($image, $quality)
{
	header("Content-Type: image/jpg");
	imageJpeg($image, '',$quality); 
}

function outputImageFile($filename)
{
	header("Content-Type: image/jpg");
	readfile($filename);
}	

?>
