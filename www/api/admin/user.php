<?php

/* REST interface for user CRUD operations */
use Alaress\Password\Service as PasswordService;
use Schoolbox\CampusMapper;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

header("Content-Type: text/xml");

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

$userMapper = UserMapper::getInstance();
$campusMapper = CampusMapper::getInstance();
$roleMapper = RoleMapper::getInstance();
$passwordService = new PasswordService();

/**
 * Setter for user fields.
 *
 * @param User  $user
 * @param array $getValues
 * @return User
 */
$setUserFields = function(User $user, $getValues) use ($roleMapper, $campusMapper, $passwordService) {
    // Update only included fields
    if (!empty($getValues['username'])) {
        $user = $user->setUsername($getValues['username']);
    }
    if (!empty($getValues['title'])) {
        $user = $user->setTitle($getValues['title']);
    }
    if (!empty($getValues['firstname'])) {
        $user = $user->setFirstname($getValues['firstname']);
    }
    if (!empty($getValues['lastname'])) {
        $user = $user->setLastname($getValues['lastname']);
    }
    if (isset($getValues['local_email'])) {
        $user = $user->setLocalEmail((bool)$getValues['local_email']);
    }
    if (!empty($getValues['alt_email'])) {
        $user = $user->setAltEmail($getValues['alt_email']);
    }
    if (!empty($getValues['position_title'])) {
        $user = $user->setPositionTitle($getValues['position_title']);
    }
    if (isset($getValues['su'])) {
        $user = $user->setSuperuser((bool)$getValues['su']);
    }
    if (!empty($getValues['database_id'])) {
        $user = $user->setExternalId($getValues['database_id']);
    }
    if (isset($getValues['year'])) {
        $user = $user->setYear($getValues['year'] == '' ? null : $getValues['year']);
    }
    if (isset($getValues['eportfolio_enabled'])) {
        $user = $user->setEportfolioEnable((bool)$getValues['eportfolio_enabled']);
    }
    if (isset($getValues['hide_contacts'])) {
        $user = $user->setHideContacts((bool)$getValues['hide_contacts']);
    }
    if (isset($getValues['hide_timetable'])) {
        $user = $user->setHideTimetable((bool)$getValues['hide_timetable']);
    }
    if (!empty($getValues['password'])) {
        $user = $user
            ->setRetrievablePassword($getValues['password'])
            ->setPassword($passwordService->hash($getValues['password']));
    }

    /* Convert campus names into ids */
    if (!empty($getValues['group'])) {
        $user = $user->setRole($roleMapper->findOneByColumn('name', $getValues['group']));
    }

    if (!empty($getValues['campus'])) {
        $user = $user->setCampusCollection(
            $campusMapper->findByColumn(
                'name',
                array_map('trim', explode(',', $getValues['campus']))
            )
        );
    }

    if (isset($getValues['display_grade_graph'])) {
        $user = $user->setDisplayGradeGraph((bool)$getValues['display_grade_graph']);
    }
    if (isset($getValues['enabled'])) {
        $user = $user->setEnabled((bool)$getValues['enabled']);
    }

    return $user;
};

if (empty($_GET['key']) || Config::get('api_key') != $_GET['key']) {
    $response = "<?xml version='1.0' standalone='yes'?>
                 <response>
                    <code>401</code>
                    <message>You are not authorized to use this service.</message>
                 </response>";

    echo $response;

} elseif (empty($_GET['action'])) {

    $response = "<?xml version='1.0' standalone='yes'?>
                 <response>
                    <code>400</code>
                    <message>The request cannot be fulfilled due to bad syntax.</message>
                 </response>";

    echo $response;
} else {

    $key = $_GET['key'];
    $action = $_GET['action'];

    /* Create operation */
    if ($action == 'create') {
        if ($userMapper->countByColumn('username', $_GET['username']) > 0) {
            $response = "<?xml version='1.0' standalone='yes'?>
                         <response>
                            <code>409</code>
                            <message>A user already exists with the same username.</message>
                         </response>";

            exit($response);
        }

        // Enable new user by default
        $user = $setUserFields($userMapper->createModel(), $_GET)
            ->setWebmail(false)
            ->setUseInternalMail(false)
            ->setEnabled(true);

        try {
            $userMapper->save($user);
            $response = "<?xml version='1.0' standalone='yes'?>
                         <response>
                            <code>201</code>
                            <message>User successfully created.</message>
                            <user>
                                <user_id>{$user->getId()}</user_id>
                            </user>
                         </response>";

        } catch (\Exception $e) {
            $message  = Scrub::xml($e->getMessage());
            $response = "<?xml version='1.0' standalone='yes'?>
                         <response>
                            <code>500</code>
                            <message>The user could not be created. (Message: {$message})</message>
                         </response>";
        }

        // TODO add response codes
        echo $response;
    }

    /* Update operation */
    elseif ($action == 'update') {

        try {
            if (!empty($_GET['user_id'])) {
                $user = $userMapper->findById($_GET['user_id']);
            } elseif (!empty($_GET['username'])) {
                $user = $userMapper->findOneByColumn('username', $_GET['username']);
            } else {
                $response = "<?xml version='1.0' standalone='yes'?>
                         <response>
                            <code>400</code>
                            <message>The request cannot be fulfilled due to bad syntax.</message>
                         </response>";
                exit($response);
            }
        } catch (\Exception $e) {
            $response = "<?xml version='1.0' standalone='yes'?>
                         <response>
                            <code>404</code>
                            <message>User not found.</message>
                         </response>";

            exit($response);
        }

        $user = $setUserFields($user, $_GET);

        try {
            $userMapper->save($user);
            $response = "<?xml version='1.0' standalone='yes'?>
                         <response>
                            <code>201</code>
                            <message>User successfully updated.</message>
                         </response>";

        } catch (\Exception $e) {
            $message  = Scrub::xml($e->getMessage());
            $response = "<?xml version='1.0' standalone='yes'?>
                         <response>
                            <code>500</code>
                            <message>The user could not be updated. (Message: {$message})</message>
                         </response>";
        }

        echo $response;
    }

    /* Delete operation */
    elseif ($action == 'delete') {
        try {
            if (!empty($_GET['user_id'])) {
                $user = $userMapper->findById($_GET['user_id']);
            } elseif (!empty($_GET['username'])) {
                $user = $userMapper->findOneByColumn('username', $_GET['username']);
            } else {
                $response = "<?xml version='1.0' standalone='yes'?>
                         <response>
                            <code>400</code>
                            <message>The request cannot be fulfilled due to bad syntax.</message>
                         </response>";
                exit($response);
            }
        } catch (\Schoolbox\Mapper\Exception $e) {
            $response = "<?xml version='1.0' standalone='yes'?>
                         <response>
                            <code>404</code>
                            <message>User not found.</message>
                         </response>";

            exit($response);
        }

        $userMapper->delete($user);

        $response = "<?xml version='1.0' standalone='yes'?>
                     <response>
                        <code>201</code>
                        <message>User successfully deleted.</message>
                     </response>";

        echo $response;
    }

    else {
        $response = "<?xml version='1.0' standalone='yes'?>
                     <response>
                        <code>404</code>
                        <message>The service requested does not exist.</message>
                     </response>";

        echo $response;
    }
}

/*
 * Test URLs
 *
 * Create
 * 192.168.36.10/api/admin/user.php?key=4357GFDSGFsdfhsufs456&action=create&username=erik&firstname=Alex&lastname=Tworowsky&title=Mr&local_email=1&alt_email=aetworowsky@gmail.com&su=1&database_id=48825&year=12&eportfolio_enabled=1&password=erik&group=Administration&campus=Senior,Junior&default_campus=Senior&enabled=1
 *
 * Update
 * 192.168.36.10/api/admin/user.php?key=4357GFDSGFsdfhsufs456&action=update&user_id=1&username=erik&firstname=Alex&lastname=Tworowsky&title=Mr&local_email=1&alt_email=aetworowsky@gmail.com&su=1&database_id=48825&year=12&eportfolio_enabled=1&password=erik&group=Administration&campus=Senior,Junior&default_campus=Senior&enabled=0
 *
 * Delete
 * 192.168.36.10/api/admin/user.php?key=4357GFDSGFsdfhsufs456&action=delete&user_id=1
 *
 */
