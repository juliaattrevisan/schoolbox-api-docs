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

if (is_numeric($this->arg[0]) ) {
    $userHandler = new User();
	$eportfolioHandler = new Eportfolio_Container($this->arg[0]);

	if (!$eportfolioHandler->canEdit($this->arg[0])) {
        error(1);
    }

    $user = $userHandler->getByID($this->arg[0]);

	$this->setFile(TEMPLATE_content, 'cms/eportfolio-image-gallery.html');
	$this->setTitle('Image Gallery for '. $user['firstname'] . ' ' . $user['lastname']);
	$this->setTab('eportfolio');

    $this->setBlock(TEMPLATE_content, 'image_gallery', 'image_gallery_block');

    $this->setVar(array(
        'FOLDER_NAME' => $user['firstname'] . ' ' . $user['lastname'],
        'USER_ID'       => $user['uid'],
    ));

	$imageList = $imageList = $eportfolioHandler->getImages($this->arg[0]);

	$count = 1;

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
