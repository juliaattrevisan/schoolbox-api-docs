<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();
$groupHandler = new Group();
$userHandler = new User();

/* Whether we should be using a system or category to add from
*/
$type = Scrub::text($_GET['type']);

/* Add into this group
 */
$destinationID = Scrub::text($_GET['destinationID']);

if ($type == 'system' && !empty($_POST['systemGroupID'])) {
    /* Add from this group...
     */
    $groupID = Scrub::text($_POST['systemGroupID']);

    $allUsers = $userHandler->getByGroup($groupID);

    if (is_array($allUsers) && count($allUsers)) {
        foreach ($allUsers as $currentUser) {
            $groupHandler->addUser($destinationID, $currentUser['id']);
        }
    }
} elseif ($type == 'individual' && !empty($_POST['individualGroupID'])) {
    /* Add from this group...
     */
    $groupID = Scrub::text($_POST['individualGroupID']);

    $allUsers = $userHandler->getAllInCategory($groupID);

    if (is_array($allUsers) && count($allUsers)) {
        foreach ($allUsers as $currentUser) {
            $groupHandler->addUser($destinationID, $currentUser['uid']);
        }
    }
}

SitePage::localRedir('/group/modify/' . $_GET['destinationID']);
