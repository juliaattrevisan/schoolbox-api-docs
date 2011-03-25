<?php
/************************************************************
*                                                           *
*         Copyright (c) 2009 Lektec Consulting         *
*                                                           *
************************************************************/

/* This page generates a photo gallery of the provided folder.	*
 * Displays one image at large size and the rest as thumbnails. *
 * Clicking the thumbnails makes them the larger images.	*
 * Clicking the large image makes it open in a new window at 	*
 * its orginal size						*/

require_once '../library/group.php';
require_once '../library/category.php';

define('IMAGESPERROW', 5);


if(is_numeric($this->arg[0]) ) {
	
	$categoryHandler = new Category;
	if(!$category = $categoryHandler->getByID($this->arg[0]))
		error(2);
	
	$groupHandler = new Group;
	if ($groupHandler->getPermission($this->arg[0]) < READ_ACCESS)
		error(6);

	$this->setFile(TEMPLATE_content, 'cms/image-gallery.html');
	$this->setTitle('Image Gallery for '.$category['name']);
	$this->setTab('resources');
	
	$this->setBlock(TEMPLATE_content, 'thumb_row', 'row_block');
	$this->setBlock('thumb_row', 'thumb_cell', 'cell_block');
	
	$this->setVar('FOLDER_NAME', $category['name']);
	$this->setVar('ID',	$category['id']);

	$imageList = $categoryHandler->getImages($category['id']);

	$count = 1; 
	if(is_array($imageList) ) {
		foreach( $imageList as $thumbnail ) {
			$this->setVar('THUMBNAIL', "<a href=\"/cms/image.php?id={$thumbnail['id']}&height=600&width=800\" rel=\"lightbox[portal]\" title=\"{$thumbnail['description']}\">
					<img src=\"/cms/image.php?id={$thumbnail['id']}&height=".Cfg::get('thumbnail_size')."&width=".Cfg::get('thumbnail_size')."&resample=0\" alt=\"{$thumbnail['description']}\" border=\"0\"></a>");
	
			$this->parse('cell_block', 'thumb_cell', true);
			if(($count % IMAGESPERROW) == 0) {
				$this->parse('row_block', 'thumb_row', true);
				$this->setVar('cell_block', '');
			}		
			
			$count++;
		}				
	
		for(;$count % IMAGESPERROW != 0; $count++) {
			$this->setVar('THUMBNAIL', '');
			$this->parse('cell_block', 'thumb_cell', true);
		}			

		$this->parse('row_block', 'thumb_row', true);
	}
	else
		$this->setVar('row_block', '');
}

else 
	error(2);  

?>
