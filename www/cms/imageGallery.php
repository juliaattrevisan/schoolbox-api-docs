<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2005 Lektec Consulting         *
*                                                           *
************************************************************/

/* This page generates a photo gallery of the provided folder.	*
 * Displays one image at large size and the rest as thumbnails. *
 * Clicking the thumbnails makes them the larger images.	*
 * Clicking the large image makes it open in a new window at 	*
 * its orginal size						*/

require_once("../include/include.php");
require_once("../include/session.php");
require_once("../include/permission.php");

define("IMAGESPERROW", 5);

class ImageGallery {

function ImageGallery($cid) {

	//display page
	$this->t_body = new Template(TPL_directory);
	$this->t_body->set_file("tplBODY", "cms/imageGallery.htm");
	$this->t_body->set_var("TITLE", HEADING." - Image Gallery");
	include("../include/page.php");	

	$db = new dbManager;

	$query = "SELECT id, name FROM category WHERE id = $cid";
	$catInfo = $db->dbGetSingleRow($query);

	$this->t_body->set_var("FOLDER_NAME", $catInfo['name']);
	$this->t_body->set_var("ID", $catInfo['id']);
	
	//output image

	if(isset($_GET['image']))
		$this->t_body->set_VAR("MAIN_IMAGE", "<a href=\"/sendframe.php?id={$_GET['image']}\" target=\"_blank\">
			<img src=\"image.php?id={$_GET['image']}&height=300&width=400\" border=\"0\"></a>");
	else	//no image pick a random one from the db
	{
		$query = "SELECT id, description FROM file WHERE category = $cid AND deleted = '0' AND INSTR(mime_type, 'image/') 
				ORDER BY RAND() LIMIT 1";
		$image = $db->dbGetSingleRow($query);	
			
		$this->t_body->set_VAR("MAIN_IMAGE", "<a href=\"/sendframe.php?id={$image['id']}\" target=\"_blank\">
			<img src=\"image.php?id={$image['id']}&height=300&width=400\" alt=\"{$image['description']}\" border=\"0\"></a>");
	}


	//output thumbnails
	$this->t_body->set_block("tplBODY", "thumb_row", "row_block");
	$this->t_body->set_block("thumb_row", "thumb_cell", "cell_block");
	
	$query = "SELECT id, description FROM file WHERE category = {$cid} AND deleted = '0' AND INSTR(mime_type, 'image/') 
		ORDER BY submitted";
	$db->dbDoQuery($query);

	$count = 1; 
	$flag = 0;

	while( $thumbnail = $db->dbGetNextArray() )
	{
		$this->t_body->set_VAR("THUMBNAIL", "<a href=\"$_PHPSELF?category={$cid}&image={$thumbnail['id']}\">
				<img src=\"image.php?id={$thumbnail['id']}&height=80&width=80&resample=0\" alt=\"{$thumbnail['description']}\" border=\"0\"></a>");


		if(($count % IMAGESPERROW) == 0)
		{
			$this->t_body->parse("cell_block", "thumb_cell", true);
			$this->t_body->parse("row_block", "thumb_row", true);
			$flag = 1;
		}		
		else if($flag)
		{
			$this->t_body->parse("cell_block", "thumb_cell", false);
			$flag = 0;
		}
		else
		{
			$this->t_body->parse("cell_block", "thumb_cell", true);
		}
	
		$count++;
	}				

	for(;$count % IMAGESPERROW != 0; $count++)
	{
		$this->t_body->set_VAR("THUMBNAIL", "");
		$this->t_body->parse("cell_block", "thumb_cell", true);
	}			

	$this->t_body->parse("row_block", "thumb_row", true);

	$this->t_body->pparse("finaloutput", "tplBODY");
	
}
}

if(isset($_GET['category']) && perm($_GET['category'], HOMEPAGESERVICE))
	$page = new ImageGallery($_GET['category']);
else 
	error(2);  

?>
