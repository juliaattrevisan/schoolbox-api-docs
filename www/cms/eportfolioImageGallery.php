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

define('IMAGESPERROW', 5);

require_once '../library/user.php';
require_once '../library/eportfolio.php';

if (is_numeric($this->arg[0]) ) {
    $userHandler = new User();
	$eportfolioHandler = new ePortfolio($this->arg[0]);

	if (!$eportfolioHandler->canEdit($this->arg[0])) {
        error(1);
    }

    $user = $userHandler->getByID($this->arg[0]);

	$this->setFile(TEMPLATE_content, 'cms/image-gallery.html');
	$this->setTitle('Image Gallery for '. $user['firstname'] . ' ' . $user['lastname']);
	$this->setTab('resources');

	$this->setBlock(TEMPLATE_content, 'thumb_row', 'row_block');
	$this->setBlock('thumb_row', 'thumb_cell', 'cell_block');

    $this->setVar(array(
        'FOLDER_NAME' => $user['firstname'] . ' ' . $user['lastname']
    ));
    
	$imageList = $imageList = $eportfolioHandler->getImages($this->arg[0]);

	$count = 1;

	if (is_array($imageList)) {
		foreach ($imageList as $thumbnail) {
			$this->setVar('THUMBNAIL', "<a href=\"/cms/image.php?myFile=1&id={$thumbnail['id']}&height=600&width=800\" rel=\"lightbox[portal]\" title=\"{$thumbnail['description']}\">
					<img src=\"/cms/image.php?myFile=1&id={$thumbnail['id']}&height=".THUMBNAIL_size."&width=".THUMBNAIL_size."&resample=0\" alt=\"{$thumbnail['description']}\" border=\"0\"></a>");

			$this->parse('cell_block', 'thumb_cell', true);

			if (($count % IMAGESPERROW) == 0) {
				$this->parse('row_block', 'thumb_row', true);
				$this->setVar('cell_block', '');
			}

			$count++;
		}

		for (;$count % IMAGESPERROW != 0; $count++) {
			$this->setVar('THUMBNAIL', '');
			$this->parse('cell_block', 'thumb_cell', true);
		}

		$this->parse('row_block', 'thumb_row', true);
	}
	else {
		$this->setVar('row_block', '');
    }
}
else {
	error(2);
}
?>
