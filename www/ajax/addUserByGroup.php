<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';
require_once SITE_root . '/www/include/stdfuncs.php';

$groupHandler = new Group();
$categoryHandler = new Category();

$suggestions = array();
$data = array();

$type = Scrub::text($_GET['type']);

if ($type == 'system') {

	/* Add from this group...
	 */
	$groupID = Scrub::text($_POST['systemGroupID']);

	$allGroups = $groupHandler->searchByName($_GET['query']);
}
else {
	/* Add from this group...
	 */
	$groupID = Scrub::text($_POST['individualGroupID']);

	$allGroups = $categoryHandler->searchByName($_GET['query']);
}

/* Accumulate results
 */
if (is_array($allGroups) && count($allGroups)) {
	foreach($allGroups as $currentGroup) {
		$suggestions[] = Scrub::text($currentGroup['name']);
		$data[] = $currentGroup['id'];
	}
}

$result = array(
	'query'	=> $_GET['query'],
	'suggestions' => $suggestions,
	'data' => $data,
);

header('Content-type: application/json');
echo json_encode($result);
