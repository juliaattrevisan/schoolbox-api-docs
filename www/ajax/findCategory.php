<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page = new SitePage();
$categoryHandler = new Category();

$suggestions = array();
$data = array();


$allResults = $categoryHandler->search('ENG10');

if (is_array($allResults) && count($allResults)) {
	foreach($allResults as $currentResult) {
		$suggestions[] = $currentResult['name'];
		$data[] = '/homepage/' . $currentResult['id'];
	}
}

$result = array(
	'query'	=> $_GET['query'],
	'suggestions' => $suggestions,
	'data' => $data
);

header('Content-type: application/json');
echo json_encode($result);

?>
