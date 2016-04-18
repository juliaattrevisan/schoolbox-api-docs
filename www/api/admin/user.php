<?php

/* REST interface for user CRUD operations */
header("Content-Type: text/xml");

require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');

$user_handler = new User();
$userMapper = \Schoolbox\UserMapper::getInstance();

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
        $duplicate = $user_handler->getByUsername($_GET['username']);

        if (!empty($duplicate)) {
            $response = "<?xml version='1.0' standalone='yes'?>
                         <response>
                            <code>409</code>
                            <message>A user already exists with the same username.</message>
                         </response>";

            exit($response);
        }

        $user = array(
            'username'			 => $_GET['username'],
            'title'				 => $_GET['title'],
            'firstname'			 => $_GET['firstname'],
            'lastname'			 => $_GET['lastname'],
            'synergy_id'		 => $_GET['database_id']==''?'NULL':$_GET['database_id'],
            'local_email'		 => $_GET['local_email'],
            'alt_email'			 => $_GET['alt_email'],
            'password'			 => $_GET['password'],
            'su'			 	 => $_GET['su'],
            'year'		 		 => $_GET['year'] == ''?'NULL':$_GET['year'],
            'eportfolio_enabled' => $_GET['eportfolio_enabled'] == 1?1:0,
            'pastoral_moderator' => $_GET['pastoral_moderator'] == 1?1:0,
            'hide_contacts'      => $_GET["hide_contacts"] == 1?1:0,
            'hide_timetable'     => $_GET["hide_timetable"] == 1?1:0,
			'enabled'			 => 1
        );

        // convert campus names into ids
        $user['group_id'] = $user_handler->getGroupID($_GET['group']);

        $campus_arr = explode(',', trim($_GET['campus']));

        if (!empty($campus_arr)) {
            foreach ($campus_arr as &$campus) {
                $user['campus'][] = $user_handler->getCampusID(trim($campus));
            }

            $user['defaultCampus'] = $user_handler->getCampusID($_GET['default_campus']);            
        }

        $id = $user_handler->create($user);
        
        if (!is_numeric($id)) {
            $response = "<?xml version='1.0' standalone='yes'?>
                         <response>
                            <code>500</code>
                            <message>The user could not be created.</message>
                         </response>";

            echo $response;
        } else {
            $response = "<?xml version='1.0' standalone='yes'?>
                         <response>
                            <code>201</code>
                            <message>User successfully created.</message>
                            <user>
                                <user_id>$id</user_id>
                            </user>
                         </response>";

            echo $response;
        }
    }

    /* Update operation */
    elseif ($action == 'update') {

        $user = $user_handler->getByID($_GET['user_id']);

        if (empty($user)) {
            $response = "<?xml version='1.0' standalone='yes'?>
                         <response>
                            <code>404</code>
                            <message>User not found.</message>
                         </response>";

            exit($response);
        }

        $user = array('id' => $_GET['user_id']);

        foreach (array('title', 'firstname', 'lastname', 'local_email', 'alt_email', 'su') as $field) {

            if (!empty($_GET[$field])) {
                $user[$field] = $_GET[$field];
            }

        }

        /* Update only included fields */
        if (!empty($_GET['username'])){
            $duplicate = $user_handler->getByUsername($_GET['username']);

            if (!empty($duplicates) && $duplicate['id'] != $user['uid']) {
                $response = "<?xml version='1.0' standalone='yes'?>
                             <response>
                                <code>409</code>
                                <message>A user already exists with the same username.</message>
                             </response>";

                exit($response);
            }

            $user['username'] = $_GET['username'];
        }

        if (is_numeric($_GET['database_id'])){
            $user['synergy_id'] = $_GET['database_id']==''?'NULL':$_GET['database_id'];
        }

        if (is_numeric($_GET['year'])) {
            $user['year'] = $_GET['year'] == '' ? 'NULL' : $_GET['year'];
        }

        if (is_numeric($_GET['eportfolio_enabled'])) {
            $user['eportfolio_enabled'] = $_GET['eportfolio_enabled'] == 1?1:0;
        }

        if (is_numeric($_GET['hide_contacts'])) {
            $user['hide_contacts'] = $_GET['hide_contacts'] == 1?1:0;
        }

        if (is_numeric($_GET['hide_timetable'])) {
            $user['hide_timetable'] = $_GET['hide_timetable'] == 1?1:0;
        }

        if (!empty($_GET['password'])) {
            $user['password'] = $_GET['password'];
        }

        /* Convert campus names into ids */
        if (!empty($_GET['group'])) {
            $user['group_id'] = $user_handler->getGroupID($_GET['group']);
        }

        if (!empty($_GET['campus'])) {
            $campus_arr = explode(',', trim($_GET['campus']));

            if (!empty($campus_arr)) {
                foreach ($campus_arr as &$campus) {
                    $user['campus'][] = $user_handler->getCampusID(trim($campus));
                }

                $user['defaultCampus'] = $user_handler->getCampusID($_GET['default_campus']);
            }
        }

		if (is_numeric($_GET['enabled'])) {
			$user['enabled'] = $_GET['enabled'] == 1 ? 1 : 0;
		}

        /* Update user and return result */
        if ($user_handler->update($user) === false) {
            $response = "<?xml version='1.0' standalone='yes'?>
                         <response>
                            <code>500</code>
                            <message>The user could not be updated.</message>
                         </response>";

            echo $response;
        } else {
            $response = "<?xml version='1.0' standalone='yes'?>
                         <response>
                            <code>201</code>
                            <message>User successfully updated.</message>
                         </response>";

            echo $response;
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

        $userMapper->delete($user['uid']);

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