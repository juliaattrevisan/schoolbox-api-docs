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

$options = getopt("sf:", array('students'));

if (!isset($options['f'])) {
    $log->error("No import filename specified");
    exit;
}

if (!file_exists($options['f'])) {
    $log->error("Specified import file not found");
    exit;
}

$loadStudents = isset($options['students']);

/**
 * @param int $externalId
 * @param string $role
 * @param int $folderId
 */
function loadUserAccess($externalId, $role, $folderId)
{
    // TODO: Apologize profusely...

    global $userMapper;
    global $userAccessMapper;
    global $log;

    // This would typically happen for a non-existent spouse, just do nothing in this case

    if (empty($externalId)) {
        return;
    }

    try {
        // Get primary contact

        $user = $userMapper->findByExternalId($externalId, $role);

        if (!isset($usersAdded[$user->getId()])) {
            // Give student access to the class

            $userAccess = $userAccessMapper->createModel();
            $userAccess->setUser($user);
            $userAccess->setServiceIdentifier($folderId);
            $userAccess->setService(UserAccessModel::SERVICE_HOMEPAGE);
            $userAccess->setAccessLevel(UserAccessModel::ACCESS_READ);
            $userAccessMapper->save($userAccess);

            $log->info("\tAdded:    {$role} '{$user->getFirstname()} {$user->getLastname()}'");
            $usersAdded[$user->getId()] = true;
        } else {
            $log->info("\tSkipping: {$role} '{$user->getFirstname()} {$user->getLastname()}' (Already Added To Folder)");
        }
    } catch (MapperException $e) {
        $log->error("\tError:    External {$role} user ID not found {$externalId}");
    }
}

$folders = [];
$classes = [];

$handle = fopen($options['f'], "r");

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
    // Couldn't read file
    $log->error("Could not locate file to import.");
    exit;
}


// Remove all students from specified classes
if ($loadStudents) {
    foreach ($classes as $folderId => $codes) {
        try {
            $folders[$folderId] = $folderMapper->findById($folderId);

            $students = $userAccessMapper->findUsersInCategory($folders[$folderId], Role::STUDENT);

            $log->info("Removing student access from '{$folders[$folderId]->getTitle()}'");

            foreach ($students as $student) {
                $userAccessMapper->deleteByColumnList([
                    'uid' => $student->getId(),
                    'sid' => $folderId
                ]);
            }
        } catch (MapperException $e) {
        }
    }
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

        if ($loadStudents) {
            $students = $classHandler->getStudents($code);

            if (is_array($students)) {
                foreach ($students as $student) {
                    loadUserAccess($student['synergy_id'], Role::STUDENT, $folderId);
                }
            }
        }

        if (is_array($parents)) {
            foreach ($parents as $parent) {
                loadUserAccess($parent['StudentContactID'], Role::PARENT, $folderId);
                loadUserAccess($parent['StudentContactSpouseID'], Role::PARENT, $folderId);
            }
        }
    }
}