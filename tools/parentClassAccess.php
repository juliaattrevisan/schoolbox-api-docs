#!/usr/bin/php
<?php

/** @var Kernel $kernel */
$kernel = require_once __DIR__ . '/../library/Bootstrap.php';

use Alaress\Application\Kernel;
use \Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Realtime\Routing\Cache\WritableCache;
use \Schoolbox\Role;
use \Schoolbox\UserMapper;
use \Schoolbox\Permission\Access\UserAccessMapper;
use \Schoolbox\Permission\Access\UserAccess as UserAccessModel;
use \Schoolbox\Resource\Folder\Mapper as FolderMapper;
use \Alaress\Lumberjack\Logger;

// Use a debug-supporting logger
$log = new Logger(STDOUT, STDERR);
$kernel->getDependencyInjector()->values['logger'] = $log;

/**
 * Retrieve all parents of students in the specified subject
 * @param $subCode
 * @return array|bool
 */
function getParents($subCode)
{
    $class = new \External_Class();
    $externalUser = new \External_User();

    $students = $class->getStudents($subCode);

    $contacts = [];

    if (is_array($students)) {
        foreach ($students as $student) {
            $contactsCurrent = $externalUser->getStudentContactList($student['synergy_id']);
            foreach ($contactsCurrent as $contactCurrent) {
                $contacts[$contactCurrent['StudentContactID']] = $contactCurrent;
            }
        }
    }

    return $contacts;
}

$userMapper = UserMapper::getInstance();
$userAccessMapper = UserAccessMapper::getInstance();
$folderMapper = FolderMapper::getInstance();
$externalUser = new \External_User();
$classHandler = new \External_Class();

// suppress the route cache mutations
$userAccessMapper->unregisterCallbacks();

$options = getopt("f:", ['students', 'dry-run']);

if (!isset($options['f'])) {
    $log->error("No import filename specified");
    exit;
}

if (!file_exists($options['f'])) {
    $log->error("Specified import file not found");
    exit;
}

$loadStudents = isset($options['students']);
$dryRun       = isset($options['dry-run']);

/**
 * @param UserMapper       $userMapper
 * @param UserAccessMapper $userAccessMapper
 * @param Logger           $log
 * @param int              $externalId
 * @param string           $role
 * @param int              $folderId
 * @param bool             $dryRun
 * @throws PDOException
 */
function loadUserAccess($userMapper, $userAccessMapper, $log, $externalId, $role, $folderId, $dryRun)
{
    // This would typically happen for a non-existent spouse, just do nothing in this case

    if (empty($externalId)) {
        return;
    }

    try {
        // Get primary contact

        $user = $userMapper->findByExternalId($externalId, $role);

        if (!isset($usersAdded[$user->getId()])) {
            // Give student access to the class

            if (!$dryRun) {
                $userAccess = $userAccessMapper->createModel();
                $userAccess->setUser($user);
                $userAccess->setServiceIdentifier($folderId);
                $userAccess->setService(UserAccessModel::SERVICE_HOMEPAGE);
                $userAccess->setAccessLevel(UserAccessModel::ACCESS_READ);
                $userAccessMapper->save($userAccess);
            }

            $log->info("\tAdded:    {$role} '{$user->getFirstname()} {$user->getLastname()}'");
            $usersAdded[$user->getId()] = true;
        } else {
            $log->info("\tSkipping: {$role} '{$user->getFirstname()} {$user->getLastname()}' (Already Added To Folder)");
        }
    } catch (MapperException $e) {
        $log->error("\tError:    External {$role} user ID not found {$externalId}");
    } catch (UnexpectedValueException $e) {
        $log->error("\tError:    {$e->getMessage()}");
    }
}

$folders = [];
$classes = [];

$handle = fopen($options['f'], 'rb');

if ($handle) {
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) > 1) {
            if (!isset($classes[$row[0]])) {
                $classes[$row[0]] = [];
            }

            for ($i = 1; $i < count($row); $i++) {
                $code = $row[$i];
                if (strlen(trim($code)) > 0) {
                    $classes[$row[0]][] = $row[$i];
                }
            }
        }
    }
    fclose($handle);
} else {
    // Couldn't read file
    $log->error('Could not locate file to import.');
    exit;
}


// Remove all students from specified classes
if ($loadStudents) {
    foreach ($classes as $folderId => $codes) {
        try {
            $folders[$folderId] = $folderMapper->findById($folderId);

            $students = $userAccessMapper->findUsersInCategory($folders[$folderId], Role::STUDENT);

            $log->info("Removing student access from '{$folders[$folderId]->getTitle()}'");

            if (!$dryRun) {
                foreach ($students as $student) {
                    $userAccessMapper->deleteByColumnList([
                        'uid' => $student->getId(),
                        'sid' => $folderId
                    ]);
                }
            }
        } catch (MapperException $e) {
            $log->warning($e->getTraceAsString());
        }
    }
}

// Remove all parents from specified classes
foreach ($classes as $folderId => $codes) {
    try {
        $folders[$folderId] = $folderMapper->findById($folderId);

        $parents = $userAccessMapper->findUsersInCategory($folders[$folderId], Role::PARENT);

        $log->info("Removing parent access from '{$folders[$folderId]->getTitle()}'");

        if (!$dryRun) {
            foreach ($parents as $parent) {
                $userAccessMapper->deleteByColumnList([
                    'uid' => $parent->getId(),
                    'sid' => $folderId
                ]);
            }
        }
    } catch (MapperException $e) {
        $log->warning($e->getTraceAsString());
    }
}

// Add parents from class codes to the specified classes

foreach ($classes as $folderId => $codes) {

    $usersAdded = [];

    foreach ($codes as $code) {
        if (!empty($code)) {
            $log->info("Adding parents of '{$code}' students to '{$folders[$folderId]->getTitle()}'");

            $parents = getParents($code);

            if ($loadStudents) {
                $students = $classHandler->getStudents($code);

                if (is_array($students)) {
                    foreach ($students as $student) {
                        loadUserAccess($userMapper, $userAccessMapper, $log, $student['synergy_id'], Role::STUDENT, $folderId, $dryRun);
                    }
                }
            }

            if (is_array($parents)) {
                foreach ($parents as $parent) {
                    loadUserAccess($userMapper, $userAccessMapper, $log, $parent['StudentContactID'], Role::PARENT, $folderId, $dryRun);
                    loadUserAccess($userMapper, $userAccessMapper, $log, $parent['StudentContactSpouseID'], Role::PARENT, $folderId, $dryRun);
                }
            }
        }
    }
}

// Reload cache (if not dry run)
if (!$dryRun) {
    /** @var WritableCache $cache */
    $cache = $kernel->get('subscriptionCache');

    try {
        $cache->cacheAllUsers(INSTANCE_ID);
    } catch (UnderflowException $e) {
        $log->notice($e->getMessage(), [ $e ]);
    }
}
