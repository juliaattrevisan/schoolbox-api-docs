<?php
require_once __DIR__ . '/../../conf/core/require-web.php';

$page = new SitePage();
$categoryMapper = new \Schoolbox\Resource\Folder\Mapper();

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
