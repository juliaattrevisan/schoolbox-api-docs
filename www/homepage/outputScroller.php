<?php
require_once SITE_root.'/library/file.php';

$this->setBlock($componentHandle, 'image', 'image_block');

/* Make sure there a config option
 */
if(empty($config['pixel'])) {
    $config['pixel'] = 222;
}

$this->setVar(array(
    'COMPONENT_PIXEL' => $config['pixel']
));

$this->parse('config_block', 'config', true);

$fileHandler = new File;

$allFiles = $fileHandler->getAllInCategory($categoryID, array('filter' => array('image')));
if(is_array($allFiles)) {
    foreach ($allFiles as $currentFile) {
        $this->setVar(array(
            'IMAGE_ID' => $currentFile['id'],
            'IMAGE_NAME' => $currentFile['description'],
            'IMAGE_DIMENSION' => $config['pixel']
        ));

        $this->parse('image_block', 'image', true);
    }
}
else {
    $this->setVar('image_block','');
}

//setup block for submission button
$this->setBlock($componentHandle, 'submit_image', 'submit_image_block');
if ($permission < WRITE_ACCESS)
	$this->setVar('submit_image_block', '');
else
	$this->parse('submit_image_block', 'submit_image');
?>