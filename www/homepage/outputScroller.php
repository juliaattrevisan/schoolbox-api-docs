<?php
require_once SITE_root . '/library/file.php';

$this->setBlock($componentHandle, 'image', 'image_block');
$this->setBlock('config', 'caption_state', 'caption_state_block');
$this->setBlock($componentHandle, 'show_caption', 'show_caption_block');
$this->setBlock('config', 'timing', 'timing_block');

/* Make sure there a config option
 */
if (empty($config['pixel'])) {
	$config['pixel'] = 222;
}

$this->setVar(array(
	'COMPONENT_PIXEL' => $config['pixel'],
    'COMPONENT_CONFIG_TIMING_VALUE_MS' => $config['timing']?$config['timing'] * 1000:'2000',
));

$this->parse('config_block', 'config', true);

$fileHandler = new File();

$allFiles = $fileHandler->getAllInCategory($categoryID, array('filter' => array('image')));

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
else {
	$this->setVar('image_block', '');
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

//setup block for submission button
$this->setBlock($componentHandle, 'submit_image', 'submit_image_block');

if ($permission < WRITE_ACCESS) {
	$this->setVar('submit_image_block', '');
}
else {
	$this->parse('submit_image_block', 'submit_image');
}

if ($config['captionState'] == 'Active') {
	$this->parse('show_caption_block', 'show_caption');
}
else {
	$this->setVar('show_caption_block', '');
}