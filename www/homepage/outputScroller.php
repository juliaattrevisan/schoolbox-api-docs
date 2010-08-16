<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/file.php';

$this->setBlock($componentHandle, 'image', 'image_block');

$fileHandler = new File;

$allFiles = $fileHandler->getAllInCategory($this->arg[0], $config = array('filter' => array('image')));

foreach ($allFiles as $currentFile) {
    $this->setVar(array(
        'IMAGE_ID' => $currentFile['id'],
        'IMAGE_NAME' => $currentFile['description']
    ));

    $this->parse('image_block', 'image', true);
}
?>