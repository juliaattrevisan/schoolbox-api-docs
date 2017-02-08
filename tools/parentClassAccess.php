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

echo "\n";

if ($handle) {
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) > 1) {
            if (!isset($classes[$row[0]])) {
                $classes[$row[0]] = [];
            }

            for ($i = 1; $i < count($row); $i++) {
                $classes[$row[0]][] = $row[$i];
            }
        }
    }
    fclose($handle);
} else {
    // Couldnt read file
    exit;
}

// Remove all parents from specified classes
foreach ($classes as $folderId => $codes) {
    try {
        $folders[$folderId] = $folderMapper->findById($folderId);

        $parents = $userAccessMapper->findUsersInCategory($folders[$folderId], \Schoolbox\Role::PARENT);

        echo "Removing parent access from '{$folders[$folderId]->getTitle()}'\n";

        foreach ($parents as $parent) {
            $userAccessMapper->deleteByColumnList([
                'uid' => $parent->getId(),
                'sid' => $folderId
            ]);
        }
    } catch (MapperException $e) {
    }
}

// Add parents from class codes to the specified classes

foreach ($classes as $folderId => $codes) {

    $usersAdded = [];

    foreach ($codes as $code) {

        echo "\nAdding parents of '{$code}' students to '{$folders[$folderId]->getTitle()}'\n\n";

        $parents = $classHandler->getParents($code);

        foreach ($parents as $parent) {
            try {

                // Get primary contact

                $userPrimary = $userMapper->findByExternalId($parent['StudentContactID']);

                if (!isset($usersAdded[$userPrimary->getId()])) {
                    // Give primary access to the class

                    $userAccess = new UserAccessModel();
                    $userAccess->setUser($userPrimary);
                    $userAccess->setServiceIdentifier($folderId);
                    $userAccess->setService(UserAccessModel::SERVICE_HOMEPAGE);
                    $userAccess->setAccessLevel(UserAccessModel::ACCESS_READ);
                    $userAccessMapper->save($userAccess);

                    echo "\tAdded:    '{$userPrimary->getFirstname()} {$userPrimary->getLastname()}'\n";
                    $usersAdded[$userPrimary->getId()] = true;
                } else {
                    echo "\tSkipping: '{$userPrimary->getFirstname()} {$userPrimary->getLastname()}' (Already Added To Folder)\n";
                }
            } catch (MapperException $e) {
            }

            try {

                // Check if there was a spouse

                if (!empty($parent['StudentContactSpouseID'])) {

                    // Get spouse

                    $userSpouse = $userMapper->findByExternalId($parent['StudentContactSpouseID']);

                    if (!isset($usersAdded[$userSpouse->getId()])) {
                        // Give spouse access to the class

                        $userAccess = new UserAccessModel();
                        $userAccess->setUser($userSpouse);
                        $userAccess->setServiceIdentifier($folderId);
                        $userAccess->setService(UserAccessModel::SERVICE_HOMEPAGE);
                        $userAccess->setAccessLevel(UserAccessModel::ACCESS_READ);
                        $userAccessMapper->save($userAccess);

                        echo "\tAdded:    '{$userSpouse->getFirstname()} {$userSpouse->getLastname()}'\n";
                        $usersAdded[$userSpouse->getId()] = true;
                    } else {
                        echo "\tSkipping: '{$userSpouse->getFirstname()} {$userSpouse->getLastname()}' (Already Added To Folder)\n";
                    }
                }
            } catch (MapperException $e) {
            }
        }
    }
}

echo "\n";