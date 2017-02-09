<?php

require_once('../library/Bootstrap.php');

use \Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role;
use \Schoolbox\UserMapper as UserMapper;
use \Schoolbox\Permission\Access\UserAccessMapper as UserAccessMapper;
use \Schoolbox\Permission\Access\UserAccess as UserAccessModel;
use \Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Alaress\Lumberjack\Logger;

$userMapper = UserMapper::getInstance();
$userAccessMapper = UserAccessMapper::getInstance();
$folderMapper = FolderMapper::getInstance();
$externalUser = new \External_User();
$classHandler = new \External_Class();

$log = new Logger(STDOUT, STDERR);

$folders = [];
$classes = [];

$handle = fopen("classes.csv", "r");

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

        $parents = $userAccessMapper->findUsersInCategory($folders[$folderId], Role::PARENT);

        $log->info("Removing parent access from '{$folders[$folderId]->getTitle()}'");

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

        $log->info("Adding parents of '{$code}' students to '{$folders[$folderId]->getTitle()}'");

        $parents = $classHandler->getParents($code);

        if (is_array($parents)) {
            foreach ($parents as $parent) {
                try {

                    // Get primary contact

                    $userPrimary = $userMapper->findByExternalId($parent['StudentContactID'], Role::PARENT);

                    if (!isset($usersAdded[$userPrimary->getId()])) {
                        // Give primary access to the class

                        $userAccess = $userAccessMapper->createModel();
                        $userAccess->setUser($userPrimary);
                        $userAccess->setServiceIdentifier($folderId);
                        $userAccess->setService(UserAccessModel::SERVICE_HOMEPAGE);
                        $userAccess->setAccessLevel(UserAccessModel::ACCESS_READ);
                        $userAccessMapper->save($userAccess);

                        $log->info("\tAdded:    '{$userPrimary->getFirstname()} {$userPrimary->getLastname()}'");
                        $usersAdded[$userPrimary->getId()] = true;
                    } else {
                        $log->info("\tSkipping: '{$userPrimary->getFirstname()} {$userPrimary->getLastname()}' (Already Added To Folder)");
                    }
                } catch (MapperException $e) {
                    $log->error("\tError:    External user ID not found {$parent['StudentContactID']}");
                }

                try {

                    // Check if there was a spouse

                    if (!empty($parent['StudentContactSpouseID'])) {

                        // Get spouse

                        $userSpouse = $userMapper->findByExternalId($parent['StudentContactSpouseID']);

                        if (!isset($usersAdded[$userSpouse->getId()])) {
                            // Give spouse access to the class

                            $userAccess = $userAccessMapper->createModel();
                            $userAccess->setUser($userSpouse);
                            $userAccess->setServiceIdentifier($folderId);
                            $userAccess->setService(UserAccessModel::SERVICE_HOMEPAGE);
                            $userAccess->setAccessLevel(UserAccessModel::ACCESS_READ);
                            $userAccessMapper->save($userAccess);

                            $log->info("\tAdded:    '{$userSpouse->getFirstname()} {$userSpouse->getLastname()}'");
                            $usersAdded[$userSpouse->getId()] = true;
                        } else {
                            $log->info("\tSkipping: '{$userSpouse->getFirstname()} {$userSpouse->getLastname()}' (Already Added To Folder)");
                        }
                    }
                } catch (MapperException $e) {
                    $log->error("\tError:    External user ID not found {$parent['StudentContactSpouseID']}");
                }
            }
        }
    }
}