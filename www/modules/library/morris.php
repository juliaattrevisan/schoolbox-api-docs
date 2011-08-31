<?php
$this->setFile(TEMPLATE_content, 'modules/library/morris.html');

require_once SITE_root.'/library/textBox.php';
$textBoxHandler = new TextBox;

$lr=$textBoxHandler->getByID(930);
$cq= $textBoxHandler->getByID(934);
$th=$textBoxHandler->getByID(933);
$dyk=$textBoxHandler->getByID(935);
$dc=$textBoxHandler->getByID(932);
$fo=$textBoxHandler->getByID(931);
$oc=$textBoxHandler->getByID(936);
$rem=$textBoxHandler->getByID(937);
$ww=$textBoxHandler->getByID(924);

$this->setVar('LETS_READ',scrubHTML($lr['body']) );
$this->setVar('CURIOUS_QUOTES', scrubHTML($cq['body']) );
$this->setVar('FINDING_OUT', scrubHTML($fo['body']) );
$this->setVar('OUR_COMMUNITY', scrubHTML($oc['body']) );
$this->setVar('GALLERY', scrubHTML($itn['body']) );
$this->setVar('DID_YOU_KNOW',scrubHTML($dyk['body']) );
$this->setVar('DIGITAL_CITIZEN',scrubHTML($dc['body']) );
$this->setVar('THINKS',scrubHTML($th['body']) );
$this->setVar('WONDERFUL_WORDS',scrubHTML($ww['body']) );
$this->setVar('REMINDER',scrubHTML($rem['body']) );

require_once SITE_root.'/library/file.php';
$this->setBlock(TEMPLATE_content, 'image', 'image_block');
$fileHandler = new File;

$allFiles = $fileHandler->getAllInCategory(4582, array('filter' => array('image')));
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
