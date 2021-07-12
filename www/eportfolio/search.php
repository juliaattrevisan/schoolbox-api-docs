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

/** @var SitePage $page */
$pageHandler = $kernel->get('sitePage');
$pageHandler->initSession();

$return = array();
if (isset($_GET['query']) && strlen($_GET['query']) && isset($_GET['groupID']) && strlen($_GET['groupID'])) {
    $groupIds = explode(';', $_GET['groupID']);
    $groupIdArray = array_map('intval', array_filter($groupIds, 'is_numeric'));

    $userMapper = new Schoolbox\UserMapper();

    $allStudents = $userMapper->findByNameAndGroup($_GET['query'], $groupIdArray);

    if ($allStudents->count()) {
        foreach ($allStudents as $currentStudent) {
            $person = $currentStudent->getFullname() . ' - ';

            $userYear = $currentStudent->getYear() ? $currentStudent->getYear()->getName() : '';

            if (is_numeric($userYear)) {
                $person .= "{$currentStudent->getYear()->getLongName()}, ";
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
