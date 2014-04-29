<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';
require_once SITE_root . '/www/include/stdfuncs.php';

$groupHandler = new Group();
$categoryHandler = new Category();

$suggestions = array();
$data = array();

if ($_GET['type'] == 'system') {

    /* Add from this group...
     */
    $groupID   = $_GET['systemGroupID'];
    $allGroups = $groupHandler->searchByName($_GET['query']);

}
else {
    /* Add from this group...
     */
    $groupID   = $_GET['individualGroupID'];
    $allGroups = $categoryHandler->searchByName($_GET['query'], true);
}

/* Accumulate results
 */
$return = array();
if (is_array($allGroups) && count($allGroups)) {
    foreach($allGroups as $currentGroup) {
        $return[] = array(
            'name' => $currentGroup['name'],
            'id'   => intval($currentGroup['id']),
        );
    }
}

header('Content-type: application/json');
echo json_encode($return);
