<?php
require_once SITE_root . '/library/file.php';

$this->setBlock($componentHandle, 'image', 'image_block');
$this->setBlock($componentHandle, 'show_caption', 'show_caption_block');
$this->setBlock($componentHandle, 'submit_image', 'submit_image_block');
$this->setBlock('config', 'caption_state', 'caption_state_block');
$this->setBlock('config', 'timing', 'timing_block');

/* Make sure there a config option
 */
if (empty($config['pixel'])) {
	$config['pixel'] = 222;
}

/* Timing values
 */
$allTimes = array(2, 5, 10, 15, 20, 30);

foreach($allTimes as $currentTime) {
	$this->setVar(array(
		'COMPONENT_CONFIG_TIMING_VALUE' => $currentTime,
		'COMPONENT_CONFIG_TIMING_SELECTED' => $currentTime == $config['timing'] ? ' selected="selected"' : '',
	));

	$this->parse('timing_block', 'timing', true);
}

$this->setVar(array(
	'COMPONENT_PIXEL' => $config['pixel'],
    'COMPONENT_CONFIG_TIMING_VALUE_MS' => $config['timing']?$config['timing'] * 1000:'2000',
));

$this->parse('config_block', 'config', true);

$fileHandler = new File();

/* Set up component name
 */
$this->setVar(array(
	'COMPONENT_NAME' => strlen(trim($config['componentname'])) ? $config['componentname'] : 'Image Scroller',

	/* These are for the Select Alternate Source resourcebrowser
	 */
	'COMPONENT_CONFIG_SRC_ID' => $config['homepage-imagescroller'],
	'COMPONENT_CONFIG_SRC_NAME' => $src['name'],
	'COMPONENT_HANDLE' => strtolower($componentHandle)
));

/* Prevent multiple galleries from other categories stacking up in the wrong place
 * and initialise empty gallery by default
 */
$this->setVar('image_block', '');

/* Select news feed from alternate source, if specified
 */
$alternateCategoryId = false;
$alternatePermissions = 0;

/* If you chose an alternate source, check the permissions on it
 */
if (strlen($config['homepage-imagescroller'])) {
	$matches = array();
	preg_match('#/homepage/(\d+)#', $config['homepage-imagescroller'], $matches);

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
	$allFiles = $fileHandler->getAllInCategory($alternateCategoryId, array('filter' => array('image')));

	/* Controls such as Post News
	 */
	if ($alternatePermissions < WRITE_ACCESS) {
		$this->setVar("submit_image_block", "");
	}
	else {
		$this->setVar(array(
			'IMAGESCROLLER_CATEGORY_ID' => $alternateCategoryId
		));

		$this->parse("submit_image_block", "submit_image");
	}
}
/* Tried to load from alternate source but failed
 */
else if (strlen($config['homepage-imagescroller']) && !$alternateCategoryId){
	$allFiles = array();

	/* Have update form to back to this homepage, not the gallery's homepage
	 */
	$this->setVar(array(
		'IMAGESCROLLER_CATEGORY_ID' => $categoryID
	));

	$this->setVar("submit_image_block", "");
}
/* Load news for the current homepage
 */
else if (!strlen($config['homepage-imagescroller'])) {
	$allFiles = $fileHandler->getAllInCategory($categoryID, array('filter' => array('image')));

	/* Have update form to back to this homepage, not the gallery's homepage
	 */
	$this->setVar(array(
		'IMAGESCROLLER_CATEGORY_ID' => $categoryID
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
	$allFiles = array();

	$this->setVar("submit_image_block", "");
}

/* Iterate
 */
if (is_array($allFiles)) {
	foreach(array('Active', 'Inactive') as $state) {
		$this->setVar(array(
			'COMPONENT_CONFIG_CAPTION_STATE_NAME' => $state,
			'COMPONENT_CONFIG_CAPTION_STATE_VALUE' => $state,
			'COMPONENT_CONFIG_CAPTION_STATE_SELECTED' => $state == $config['captionState'] ? ' selected="selected"' : ''
		));

		$this->parse("caption_state_block", "caption_state", true);
	}

	foreach($allFiles as $currentFile) {
		$this->setVar(array(
			'IMAGE_ID' => $currentFile['id'],
			'IMAGE_NAME' => $currentFile['description'],
			'IMAGE_DIMENSION' => $config['pixel']
		));

		$this->parse('image_block', 'image', true);
	}
}

/* Display captions
 */
if ($config['captionState'] == 'Active') {
	$this->parse('show_caption_block', 'show_caption');
}
else {
	$this->setVar('show_caption_block', '');
}