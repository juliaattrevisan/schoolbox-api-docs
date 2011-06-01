<?php
define('IMAGESPERROW', 4);

//output thumbnails
/*
$this->setBlock($componentHandle, "thumb_row", "row_block");
$this->setBlock("thumb_row", "thumb_cell", "cell_block");
*/
$this->setBlock($componentHandle, 'image_gallery', 'image_gallery_block');

if(is_numeric($config['size']))
    $limit = $config['size'] * IMAGESPERROW;

$imageList = $categoryHandler->getImages($categoryID, $limit);

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
}
else {
	$this->setVar('image_gallery_block', '');
}

//setup block for submission button
$this->setBlock($componentHandle, "submit_image", "submit_image_block");
if ($permission < WRITE_ACCESS)
	$this->setVar("submit_image_block", "");
else
	$this->parse("submit_image_block", "submit_image");

$this->setVar(array(
	'IMAGE_HEIGHT' => Cfg::get('thumbnail_size'),
));

?>