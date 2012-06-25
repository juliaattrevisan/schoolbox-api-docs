<?php
define('IMAGESPERROW', 4);

$this->setBlock($componentHandle, 'image_gallery', 'image_gallery_block');
$this->setBlock($componentHandle, "submit_image", "submit_image_block");

if (is_numeric($config['size'])) {
	$limit = $config['size'] * IMAGESPERROW;
}

/* Set up component name
 */
$this->setVar(array(
	'COMPONENT_NAME' => strlen(trim($config['componentname'])) ? $config['componentname'] : 'Image Gallery',

	/* These are for the Select Alternate Source resourcebrowser
	 */
	'COMPONENT_CONFIG_SRC_ID' => $config['homepage-imagegallery'],
	'COMPONENT_CONFIG_SRC_NAME' => $src['name'],
	'COMPONENT_HANDLE' => strtolower($componentHandle)
));

/* Prevent multiple galleries from other categories stacking up in the wrong place
 * and initialise empty gallery by default
 */
$this->setVar('image_gallery_block', '');

/* Select news feed from alternate source, if specified
 */
$alternateCategoryId = false;
$alternatePermissions = 0;

/* If you chose an alternate source, check the permissions on it
 */
if (strlen($config['homepage-imagegallery'])) {
	$matches = array();
	preg_match('#/homepage/(\d+)#', $config['homepage-imagegallery'], $matches);

	if (count($matches)) {
		$alternatePermissions = $groupHandler->getPermission($matches[1]);

		if ($alternatePermissions >= READ_ACCESS) {
			$alternateCategoryId = $matches[1];
		}
	}
}

/* Load images from some other homepage
 */
if ($alternateCategoryId) {
	$imageList = $categoryHandler->getImages($alternateCategoryId, $limit);

	/* Controls such as Post News
	 */
	if ($alternatePermissions < WRITE_ACCESS) {
		$this->setVar("submit_image_block", "");
	}
	else {
		$this->setVar(array(
			'IMAGEGALLERY_CATEGORY_ID' => $alternateCategoryId
		));

		$this->parse("submit_image_block", "submit_image");
	}
}
/* Tried to load from alternate source but failed
 */
else if (strlen($config['homepage-imagegallery']) && !$alternateCategoryId){
	$imageList = array();

	/* Have update form to back to this homepage, not the gallery's homepage
	 */
	$this->setVar(array(
		'IMAGEGALLERY_CATEGORY_ID' => $categoryID
	));

	$this->setVar("submit_image_block", "");
}
/* Load news for the current homepage
 */
else if (!strlen($config['homepage-imagegallery'])) {
	$imageList = $categoryHandler->getImages($categoryID, $limit);

	/* Have update form to back to this homepage, not the gallery's homepage
	 */
	$this->setVar(array(
		'IMAGEGALLERY_CATEGORY_ID' => $categoryID
	));

	/* Controls such as Post News
	 */
	if ($permission < WRITE_ACCESS) {
		$this->setVar("submit_image_block", "");
	}
	else {
		$this->parse("submit_image_block", "submit_image");
	}
}
/* Default
 */
else {
	$imageList = array();

	$this->setVar("submit_image_block", "");
}

/* Iterate
 */
if (is_array($imageList)) {
	foreach($imageList as $thumbnail) {
		$this->setVar(array(
			'THUMBNAIL_ID' => $thumbnail['id'],
			'THUMBNAIL_DESCRIPTION' => $thumbnail['description'],
			'THUMBNAIL_SIZE' =>	Cfg::get('thumbnail_size'),
			'IMAGE_HEIGHT' => Cfg::get('thumbnail_size'),
		));

		$this->parse('image_gallery_block', 'image_gallery', true);
	}
}
