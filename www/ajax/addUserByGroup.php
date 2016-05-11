<?php
require_once dirname(__FILE__) . '/../../library/Bootstrap.php';
require_once SITE_root . '/www/include/stdfuncs.php';

$groupHandler = new Group();
$categoryMapper = new \Schoolbox\Resource\Folder\Mapper();

$suggestions = array();
$data = array();
$return = array();

if ($_GET['type'] == 'system') {
/* Add from this group...
     */
    $groupID   = $_GET['systemGroupID'];
    $allGroups = $groupHandler->searchByName($_GET['query']);


    if (is_array($allGroups) && count($allGroups)) {
        foreach ($allGroups as $currentGroup) {
            $return[] = array(
                'name' => $currentGroup['name'],
                'id'   => intval($currentGroup['id']),
            );
        }
    }
} else {
    /* Add from this group...
     */
    $groupID   = $_GET['individualGroupID'];
    $allGroups = $categoryMapper->searchByName($_GET['query'], true);

    foreach ($allGroups as $currentGroup) {
        $return[] = array(
            'name' => $currentGroup->getName(),
            'id'   => intval($currentGroup->getId()),
        );
    }
}

/* Accumulate results
 */

header('Content-type: application/json');
echo json_encode($return);
