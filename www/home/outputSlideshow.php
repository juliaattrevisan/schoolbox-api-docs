<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/file.php';

$this->setBlock($component['name'], 'image', 'image_block');

$fileHandler = new File;

$allFiles = $fileHandler->getAllInCategory(Cfg::get('welcome_image_folder'), array('filter' => array('image')));
if(is_array($allFiles)) {
    foreach ($allFiles as $currentFile) {
        $this->setVar(array(
            'IMAGE_ID' => $currentFile['id'],
            'IMAGE_NAME' => $currentFile['description'],
            'IMAGE_DIMENSION' => 230,
        ));

        $this->parse('image_block', 'image', true);
    }

    $this->parse('POS'.$component['position'], $component['name']);
}
else {
    $this->setVar('POS'.$component['position'], '');
}
?>