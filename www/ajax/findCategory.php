<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page = new SitePage();
$categoryHandler = new Category();

$suggestions = array();
$data = array();

$allResults = $categoryHandler->searchByName($_GET['query']);

$return = array();
if (is_array($allResults) && count($allResults)) {
    foreach($allResults as $currentResult) {
        $return[] = array(
            'id'   => '/homepage/' . $currentResult['id'],
            'name' => $currentResult['name'] . ' - /homepage/' . $currentResult['id'],
        );
    }
}

header('Content-type: application/json');
echo json_encode($return);
