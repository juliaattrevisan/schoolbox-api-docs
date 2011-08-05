<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page = new SitePage();
$categoryHandler = new Category();

$suggestions = array();
$data = array();

$group = $_GET['groupID'];
$query = $_GET['query'];

//help steve!
$allResults = $categoryHandler->search($group);

if (is_array($allResults) && count($allResults)) {
	foreach($allResults as $currentResult) {
		$suggestions[] = $currentResult['name'];
		$data[] = '/homepage/' . $currentResult['id'];
	}
}

$result = array(
	'query'	=> $query,
	'suggestions' => $suggestions,
	'data' => $data
);

header('Content-type: application/json');
echo json_encode($result);

?>
