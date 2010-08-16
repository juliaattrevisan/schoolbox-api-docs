<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/file.php';

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

$allFiles = $fileHandler->getAllInCategory($this->arg[0], array('filter' => array('image')));

foreach ($allFiles as $currentFile) {
    $this->setVar(array(
        'IMAGE_ID' => $currentFile['id'],
        'IMAGE_NAME' => $currentFile['description'],
        'IMAGE_DIMENSION' => $config['pixel']
    ));

    print_r($config);

    $this->parse('image_block', 'image', true);
}
?>