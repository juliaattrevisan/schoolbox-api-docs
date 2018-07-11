<?php

use Schoolbox\Role;
use Schoolbox\UserMapper;

require_once dirname(__FILE__) . '/../../library/Bootstrap.php';

// First lets grab some roles
$roleMapper = \Schoolbox\RoleMapper::getInstance();

/** @var \Schoolbox\Collection|Role[] $roles */
$roles = $roleMapper->findAll();


// ------------------------------------------------------------
// Groups/Homepages
// ------------------------------------------------------------
$page           = new SitePage();
$categoryMapper = new \Schoolbox\Resource\Folder\Mapper();

$suggestions = [];
$data        = [];

$allResults = $categoryMapper->searchByName($_GET['query']);

$return = [];

// Include all the folders by themselves
foreach ($allResults as $currentResult) {
    // Group users
    $return[] = [
        'id'   => 'folder_' . $currentResult->getId(),
        'name' => $currentResult->getName() . ' - /homepage/' . $currentResult->getId(),
        'type' => 'homepage',
    ];

    // Parents of users in the group
    $return[] = [
        'id'   => 'folder_' . $currentResult->getId() . '_parents',
        'name' => 'Parents of ' . $currentResult->getName() . ' - /homepage/' . $currentResult->getId(),
        'type' => 'homepage',
    ];
}


// ------------------------------------------------------------
// Users
// ------------------------------------------------------------
$userHandler  = new User();
$groupHandler = new Group();
$pageHandler  = new SitePage();
$emailObj     = new Email();
$userMapper   = UserMapper::getInstance();

$result = $suggestions = $data = [];

$excludeID          = $_GET['excludeID'];
$keyword            = $_GET['query'];
$groupID            = $_GET['groupID'];
$roleTypeID         = $_GET['roleTypeID'];
$showCampus         = !empty($_GET['showCampus']);
$residentialHouseID = $_GET['residentialHouseID'];

if (!empty($residentialHouseID)) {
    $residentialHouseID = explode(';', $residentialHouseID);
}

$filterParents = false;

if (is_string($keyword) && strlen($keyword) > 1 && strlen($keyword) < 64 && (strlen($groupID) || strlen($roleTypeID))) {

    /** @var \Schoolbox\Collection | \Schoolbox\User[] $userList */
    $userList = $userMapper->findByNameAndGroup($keyword, [1, 2, 3, 4, 5, 6]);

    $studentPrefixes = ["Parents of ", "Teachers of "];

    foreach ($userList as $user) {
        $return[] = [
            'id'   => 'user_' . $user->getId(),
            'name' => $user->getFullName(),
        ];

        if ($user->getRole()->getType() == 'student') {
            foreach ($studentPrefixes as $studentPrefix) {
                $return[] = [
                    'id'   => $studentPrefix . '_user_' . $user->getId(),
                    'name' => $studentPrefix . $user->getFullName(),
                ];
            }
        }
    }

}


// ------------------------------------------------------------
// Output
// ------------------------------------------------------------
header('Content-type: application/json');
echo json_encode($return);