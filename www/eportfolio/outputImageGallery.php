<?php
define('IMAGESPERROW', 4);

/* Output thumbnails
 */
$this->setBlock($componentHandle, "thumb_row", "row_block");
$this->setBlock("thumb_row", "thumb_cell", "cell_block");

if (is_numeric($config['size'])) {
    $limit = $config['size'] * IMAGESPERROW;
}

$imageList = $imageList = $eportfolioHandler->getImages($userID);

$count = 1;

if (is_array($imageList) ) {
	foreach ($imageList as $thumbnail) {
		$this->setVar('THUMBNAIL', "
			<a class=\"thumb\" href=\"/cms/image.php?myFile=1&id={$thumbnail['id']}&height=600&width=800\" rel=\"lightbox[portal]\" title=\"{$thumbnail['description']}\">
			<img src=\"/cms/image.php?myFile=1&id={$thumbnail['id']}&height=".Cfg::get('thumbnail_size')."&width=".Cfg::get('thumbnail_size')."&resample=0\" alt=\"{$thumbnail['description']}\" border=\"0\">
			</a>");
	
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

?>