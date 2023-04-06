<?php

/**
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// Enforce direct script access
if (isset($this)) {
    /** @var SitePage $this */
    $this->pageNotFound();
}

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $pageHandler */
$pageHandler = $kernel->get('sitePage');
$pageHandler->initSession();

$return = array();
if (isset($_GET['query']) && strlen($_GET['query']) && isset($_GET['groupID']) && strlen($_GET['groupID'])) {
    $groupIds = explode(';', $_GET['groupID']);
    $groupIdArray = array_map('intval', array_filter($groupIds, 'is_numeric'));

    $userMapper = new Schoolbox\UserMapper();

    $allStudents = $userMapper->findByNameAndGroup($_GET['query'], $groupIdArray);
    $yearPrefix  = $pageHandler->getLocalisedTerm('year', false, true);

    if ($allStudents->count()) {
        /** @var \Schoolbox\User $currentStudent */
        foreach ($allStudents as $currentStudent) {
            $person = $currentStudent->getFullname() . ' - ';

            if ($currentStudent->getYear()) {
                $person .= "{$currentStudent->getYear()->getLongName($yearPrefix)}, ";
            }

            $person .= $currentStudent->getRole()->getName();

            $return[] = array(
                'id'   => $currentStudent->getId(),
                'name' => $person,
            );
        }
    }
}

header('Content-type: application/json');
echo json_encode($return);
