<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page = new SitePage();
$categoryMapper = new \Schoolbox\Resource\CategoryMapper();

$suggestions = array();
$data = array();

$allResults = $categoryMapper->searchByName($_GET['query']);

$return = array();

foreach ($allResults as $currentResult) {
    $return[] = array(
        'id'   => '/homepage/' . $currentResult->getId(),
        'name' => $currentResult->getName() . ' - /homepage/' . $currentResult->getId(),
    );
}

header('Content-type: application/json');
echo json_encode($return);
