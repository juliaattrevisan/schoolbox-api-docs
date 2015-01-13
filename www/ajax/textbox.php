<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page          = new SitePage;
$textboxMapper = new \Schoolbox\Textbox\Mapper();
$return        = array();

$allTextboxes = $textboxMapper->search($_GET['query']);

foreach ($allTextboxes as $currentTextbox) {
    $return[] = array(
        'id'   => $currentTextbox->getId(),
        'name' => "{$currentTextbox->getCategory()->getName()}: {$currentTextbox->getHeading()}",
    );
}

header('Content-type: application/json');
echo json_encode($return);
