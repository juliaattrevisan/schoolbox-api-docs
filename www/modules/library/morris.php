<?php

$library = new Library_Splash('morris', 4582);

$library->setVars(array(
    'LETS_READ'         => 930,
    'CURIOUS_QUOTES'    => 934,
    'FINDING_OUT'       => 933,
    'OUR_COMMUNITY'     => 935,
    'GALLERY'           => 932,
    'DID_YOU_KNOW'      => 935,
    'DIGITAL_CITIZEN'   => 936,
    'THINKS'            => 937,
    'WONDERFUL_WORDS'   => 924,
    'REMINDER'          => 937
));

$library->render($this);


/*
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

$this->setVar('LETS_READ',Scrub::html($lr['body']) );
$this->setVar('CURIOUS_QUOTES', Scrub::html($cq['body']) );
$this->setVar('FINDING_OUT', Scrub::html($fo['body']) );
$this->setVar('OUR_COMMUNITY', Scrub::html($oc['body']) );
$this->setVar('GALLERY', Scrub::html($itn['body']) );
$this->setVar('DID_YOU_KNOW',Scrub::html($dyk['body']) );
$this->setVar('DIGITAL_CITIZEN',Scrub::html($dc['body']) );
$this->setVar('THINKS',Scrub::html($th['body']) );
$this->setVar('WONDERFUL_WORDS',Scrub::html($ww['body']) );
$this->setVar('REMINDER',Scrub::html($rem['body']) );

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
*/
?>
