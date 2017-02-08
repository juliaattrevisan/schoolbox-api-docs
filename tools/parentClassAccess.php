<?php

require_once('../library/Bootstrap.php');

use \Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\UserMapper as UserMapper;
use \Schoolbox\Permission\Access\UserAccessMapper as UserAccessMapper;
use \Schoolbox\Permission\Access\UserAccess as UserAccessModel;
use \Schoolbox\Resource\Folder\Mapper as FolderMapper;

$userMapper = UserMapper::getInstance();
$userAccessMapper = UserAccessMapper::getInstance();
$folderMapper = FolderMapper::getInstance();
$externalUser = new \External_Synergetic_User();
$classHandler = new \External_Synergetic_Class();

$folders = [];
$classes = [];

$handle = fopen("classes.txt", "r");

if ($handle) {
    while (($line = fgets($handle)) !== false) {
        $classes[] = trim($line);
    }
    fclose($handle);
} else {
    // Couldnt read file
    exit;
}

// Remove all parents from specified classes
foreach ($classes as $code) {
    try {
        $folders[$code] = $folderMapper->findByCode($code);

        $parents = $userAccessMapper->findUsersInCategory($folders[$code], \Schoolbox\Role::PARENT);

        foreach ($parents as $parent) {
            $userAccessMapper->deleteByColumnList([
                'uid' => $parent->getId(),
                'sid' => $folders[$code]->getId()
            ]);
        }
    } catch (MapperException $e) {
    }
}

// Add current parents to the specified classes

foreach ($classes as $code) {
    $parents = $classHandler->getParents($code);

    foreach ($parents as $parent) {
        try {

            // Get primary contact

            $userPrimary = $userMapper->findByExternalId($parent['StudentContactID']);

            // Give primary access to the class

            $userAccess = new UserAccessModel();
            $userAccess->setUser($userPrimary);
            $userAccess->setServiceIdentifier($folders[$code]->getId());
            $userAccess->setService(UserAccessModel::SERVICE_HOMEPAGE);
            $userAccess->setAccessLevel(UserAccessModel::ACCESS_READ);
            $userAccessMapper->save($userAccess);

        } catch (MapperException $e) {
        }

        try {

            // Check if there was a spouse

            if (!empty($parent['StudentContactSpouseID'])) {

                // Get spouse

                $userSpouse = $userMapper->findByExternalId($parent['StudentContactSpouseID']);

                // Give spouse access to the class

                $userAccess = new UserAccessModel();
                $userAccess->setUser($userSpouse);
                $userAccess->setServiceIdentifier($folders[$code]->getId());
                $userAccess->setService(UserAccessModel::SERVICE_HOMEPAGE);
                $userAccess->setAccessLevel(UserAccessModel::ACCESS_READ);
                $userAccessMapper->save($userAccess);
            }
        } catch (MapperException $e) {
        }
    }
}