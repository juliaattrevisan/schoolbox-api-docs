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


if (is_numeric($this->arg[0])) {

    $groupHandler = new Group;
	$categoryHandler = new Category;

	if(!$category = $categoryHandler->getByID($this->arg[0])) {
		error(2);
	}
	if ($groupHandler->getPermission($this->arg[0]) < READ_ACCESS) {
	    error(6);
	}

	$this->setFile(TEMPLATE_content, 'cms/image-gallery.html');
	$this->setTitle('Image Gallery for '.$category['name']);
	$this->setTab('resources');
	
	$this->setBlock(TEMPLATE_content, 'image_gallery', 'image_gallery_block');
	
	$this->setVar('FOLDER_NAME', $category['name']);
	$this->setVar('ID',	$category['id']);

	$imageList = $categoryHandler->getImages($category['id']);

	if (is_array($imageList)) {
		foreach($imageList as $thumbnail) {
			$this->setVar(array(
				'THUMBNAIL_ID' => $thumbnail['id'],
				'THUMBNAIL_DESCRIPTION' => $thumbnail['description'],
				'THUMBNAIL_SIZE' =>	Cfg::get('thumbnail_size'),
			));
	
			$this->parse('image_gallery_block', 'image_gallery', true);
		}
		
		$this->setVar(array(
			'IMAGE_HEIGHT' => Cfg::get('thumbnail_size'),
		));
	}
	else {
		$this->setVar('image_gallery_block', '');
	}
}
else {
	error(2);  
}
?>
