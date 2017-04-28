<?php

/* REST interface for user CRUD operations */
use Schoolbox\CampusMapper;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;

header("Content-Type: text/xml");

require_once(dirname(__FILE__) . '/../../../library/Bootstrap.php');

$user_handler = new User();
$userMapper = UserMapper::getInstance();
$campusMapper = CampusMapper::getInstance();
$roleMapper = RoleMapper::getInstance();

if (empty($_GET['key']) || Cfg::get('api_key') != $_GET['key']) {
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

        $user = $userMapper->createModel()
            ->setUsername($_GET['username'])
            ->setTitle($_GET['title'])
            ->setFirstname($_GET['firstname'])
            ->setLastname($_GET['lastname'])
            ->setExternalId($_GET['database_id'] == '' ? null : $_GET['database_id'])
            ->setLocalEmail($_GET['local_email'])
            ->setAltEmail($_GET['alt_email'])
            ->setPositionTitle($_GET['position_title'])
            // Figure out password setting
            ->setPassword($_GET['password'])
            ->setSuperuser($_GET['su'])
            ->setYear($_GET['year'] == '' ? null : $_GET['year'])
            ->setEportfolioEnable((bool)$_GET['eportfolio_enabled'])
            ->setHideContacts((bool)$_GET['hide_contacts'])
            ->setHideTimetable((bool)$_GET['hide_timetable'])
            ->setEnabled(true)
            ->setRole($roleMapper->findById($_GET['group']))
            ->setDisplayGradeGraph($_GET['display_grade_graph'])
            ->setCampusCollection(
                $roleMapper->findByColumn(
                    'id',
                    array_map('trim', explode(',', $_GET['campus']))
                )
            )
        ;

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
            $user = $userMapper->findById($_GET['user_id']);
        } catch (\Exception $e) {
            $response = "<?xml version='1.0' standalone='yes'?>
                         <response>
                            <code>404</code>
                            <message>User not found.</message>
                         </response>";

            exit($response);
        }

        /* Update only included fields */
        if (!empty($_GET['title'])) {
            $user = $user->setTitle($_GET['title']);
        }
        if (!empty($_GET['firstname'])) {
            $user = $user->setFirstname($_GET['firstname']);
        }
        if (!empty($_GET['lastname'])) {
            $user = $user->setLastname($_GET['lastname']);
        }
        if (!empty($_GET['local_email'])) {
            $user = $user->setLocalEmail($_GET['local_email']);
        }
        if (!empty($_GET['alt_email'])) {
            $user = $user->setAltEmail($_GET['alt_email']);
        }
        if (!empty($_GET['position_title'])) {
            $user = $user->setPositionTitle($_GET['position_title']);
        }
        if (!empty($_GET['su'])) {
            $user = $user->setSuperuser($_GET['su']);
        }
        if (!empty($_GET['username'])) {
            $user = $user->setUsername($_GET['username']);
            // Username duplicate handling should be done in mapper presave
        }
        if (is_numeric($_GET['database_id'])) {
            $user = $user->setExternalId($_GET['database_id']=='' ? null : $_GET['database_id']);
        }
        if (is_numeric($_GET['year'])) {
            $user = $user->setYear($_GET['year'] == '' ? null : $_GET['year']);
        }
        if (is_numeric($_GET['eportfolio_enabled'])) {
            $user = $user->setEportfolioEnable((bool)$_GET['eportfolio_enabled']);
        }
        if (is_numeric($_GET['hide_contacts'])) {
            $user = $user->setHideContacts((bool)$_GET['hide_contacts']);
        }
        if (is_numeric($_GET['hide_timetable'])) {
            $user = $user->setHideTimetable((bool)$_GET['hide_timetable']);
        }
        if (!empty($_GET['password'])) {
            // Figure out password setting
            $user = $user->setPassword($_GET['password']);
        }

        /* Convert campus names into ids */
        if (!empty($_GET['group'])) {
            $user = $user->setRole($roleMapper->findById($_GET['group']));
        }

        if (!empty($_GET['campus'])) {
            $user = $user->setCampusCollection(
                $roleMapper->findByColumn(
                    'id',
                    array_map('trim', explode(',', $_GET['campus']))
                )
            );
        }

        if (is_numeric($_GET['display_grade_graph'])) {
            $user = $user->setDisplayGradeGraph((bool)$_GET['display_grade_graph']);
        }
        if (is_numeric($_GET['enabled'])) {
            $user = $user->setEnabled((bool)$_GET['enabled']);
        }

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
