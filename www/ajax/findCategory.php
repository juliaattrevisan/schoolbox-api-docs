<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page = new SitePage();
$categoryHandler = new Category();

$data = array();
$allResults = $categoryHandler->search('ENG10');

if (is_array($allResults) && count($allResults)) {
	foreach($allResults as $currentResult) {
		$data[] = array(
			'id' => $currentResult['id'],
			'name' => $currentResult['name'],
			'url' => '/homepage/' . $currentResult['id'],
		);
	}
}

print_r($data);

?>
