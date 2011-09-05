<?php
$this->setFile(TEMPLATE_content, 'modules/library/nina.html');

require_once SITE_root.'/library/textBox.php';
$textBoxHandler = new TextBox;

$or=$textBoxHandler->getByID(530);
$rr= $textBoxHandler->getByID(531);
$nb=$textBoxHandler->getByID(940);
$qf=$textBoxHandler->getByID(532);
$itn=$textBoxHandler->getByID(534);
$rt=$textBoxHandler->getByID(941);
$cv=$textBoxHandler->getByID(942);

$this->setVar('ONLINE_RESOURCES',Scrub::html($or['body']) );
$this->setVar('REMARKABLE_READING', Scrub::html($rr['body']) );
$this->setVar('NEW_BOOKS', Scrub::html($nb['body']) );
$this->setVar('QUICK_FACTS', Scrub::html($qf['body']) );
$this->setVar('IN_THE_NEWS', Scrub::html($itn['body']) );
$this->setVar('RESEARCH_TOOLS',Scrub::html($rt['body']) );
$this->setVar('CLICK_VIEW',Scrub::html($cv['body']) );

require_once SITE_root.'/library/file.php';
$this->setBlock(TEMPLATE_content, 'image', 'image_block');
$fileHandler = new File;

$allFiles = $fileHandler->getAllInCategory(4276, array('filter' => array('image')));
if(is_array($allFiles)) {
    foreach ($allFiles as $currentFile) {
        $this->setVar(array(
            'IMAGE_ID' => $currentFile['id'],
            'IMAGE_NAME' => $currentFile['description'],
        ));

        $this->parse('image_block', 'image', true);
    }
}
else {
    $this->setVar('image_block','');
}



?>
