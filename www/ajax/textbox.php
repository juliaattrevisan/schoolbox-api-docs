<?php
require_once dirname(__FILE__) . '/../../library/Bootstrap.php';

$page          = new SitePage;
$textboxMapper = new \Schoolbox\Resource\Textbox\Mapper();
$return        = array();

$allTextboxes = $textboxMapper->search($_SESSION['user'], $_GET['query']);

foreach ($allTextboxes->getResult() as $currentTextbox) {
    $return[] = array(
        'id'   => $currentTextbox->getId(),
        'name' => "{$currentTextbox->getCategory()->getName()}: {$currentTextbox->getHeading()}",
    );
}

header('Content-type: application/json');
echo json_encode($return);
