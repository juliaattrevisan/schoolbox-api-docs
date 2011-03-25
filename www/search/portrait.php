<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//this script outputs an image of the user

//includes
require_once("../include/image.php");
require_once("../../include/sitePage.php");
require_once("../../library/group.php");
require_once("../../library/user.php");
$page = new SitePage;

$groupHandler = new Group;
$userHandler = new User;




//no image
define(NO_IMAGE, $_SERVER['DOCUMENT_ROOT'].'/images/no_portrait.jpg');
if(is_numeric($_GET['height']))
	$height = $_GET['height'];
else
	$height = 300;
	
if(is_numeric($_GET['width']))
	$width = $_GET['width'];
else
	$width = 200;	
	
$imageHandler = new Image;

if(is_numeric($_GET['id']) && SYNERGY_enable)
{

    $user = $userHandler->getBySynergy($_GET['id']);

    if( $groupHandler->isStaff() || $groupHandler->isParentOfChild($user['uid'])) {

        //get file details
        $db = mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);

        //needs to check file permissions otherwise we have security problem
        $query = "	SELECT vPhotos.ID, vPhotos.Photo, vPhotos.PhotoType
                    FROM SynergyOne.dbo.vPhotos vPhotos
                    WHERE vPhotos.ID = {$_GET['id']}";
        $result = @mssql_query($query, $db);
        if(@mssql_num_rows($result) == 1)
        {

            $image = mssql_fetch_array($result);
            $tempFile = tempnam(Cfg::get('zip_dir'), 'synport');
            $handle = fopen($tempFile, "w");
            fwrite($handle, $image['Photo']);
            fclose($handle);

            $outImage = $imageHandler->imageResize($tempFile, $height, $width, false);
            unlink($tempFile);

            header("Content-Type: image/jpg");
            header("Content-Disposition: filename={$_GET['id']}.jpg");
            //header ("Content-Length: $filesize");
            imagejpeg($outImage);

            exit;
        }
    }
}

//if we get to here just output the no image
if(file_exists(NO_IMAGE))
{
	$outImage = $imageHandler->imageResize(NO_IMAGE, $height, $width, false);
	header("Content-Type: image/jpg");
	header("Content-Disposition: filename=nopic.jpg"); 
	imagejpeg($outImage);
}		

?>
