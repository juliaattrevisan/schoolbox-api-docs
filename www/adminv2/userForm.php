<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once SITE_root . '/library/group.php';
require_once SITE_root . '/library/user.php';

$page = new SitePage;
$userHandler = new User;
$groupHandler = new Group;

/* Superusers only */
if(!$groupHandler->isSuperuser())
    error(19);

/* Add user to group
 */
if (($_POST['groupSubmit'] == 'Add') && $_POST['uid']) {
    $groupHandler->setService($_POST['service']);
    $groupHandler->addUser($_POST['sid'], $_POST['uid'], $_POST['type']);

	localRedir('/adminv2/modify-user/'.$_POST['uid']);
}

/* Update existing user details
 */
else if(is_numeric($_POST['userID']) && $_POST['username'] ) {
    if ($_POST['delete']) {
        $userHandler->delete($_POST['userID']);

        localRedir('/adminv2/user/');
    }

    $user = array(
		'id'				 => $_POST['userID'],
		'username'			 => $_POST['username'],
		'title'				 => $_POST['title'],
		'firstname'			 => $_POST['firstname'],
		'lastname'			 => $_POST['lastname'],
		'synergy_id'		 => $_POST['database_id'],
		'local_email'		 => $_POST['local_email'],
		'alt_email'			 => $_POST['alt_email'],
		'group_id'			 => $_POST['group'],
		'password'			 => $_POST['password'],
		'campus'			 => $_POST['campus'],
		'su'				 => $_POST['superuser'],
		'year'				 => $_POST['year'],
		'eportfolio_enabled' => $_POST['eportfolio']
    );

    $userHandler->update($user);

    localRedir('/adminv2/modify-user/'.$user['id']);
}

/* Create new user
 */
else if ($_POST['username']) {
    $user = array(
		'username'			 => $_POST['username'],
		'title'				 => $_POST['title'],
		'firstname'			 => $_POST['firstname'],
		'lastname'			 => $_POST['lastname'],
		'synergy_id'		 => $_POST['database_id'],
		'local_email'		 => $_POST['local_email'],
		'alt_email'			 => $_POST['alt_email'],
		'group_id'			 => $_POST['group'],
		'password'			 => $_POST['password'],
		'campus'			 => $_POST['campus'],
		'su'			 	 => $_POST['superuser'],
		'year'		 		 => $_POST['year'],
		'eportfolio_enabled' => $_POST['eportfolio']
    );

    $id = $userHandler->create($user);

    localRedir('/adminv2/modify-user/'.$id);
}

/* Ajax Update groups and their permissions etc
 */
else if ($_POST['updateGroups'] == true) {
    $groupHandler->setService($_POST['service']);
    $groupHandler->setUserGroupType($_POST['sid'], $_POST['uid'], $_POST['type']);
}

/* Switch logins to the current user being viewed
 */
else if($_POST['login']) {
    require_once SITE_root . '/include/session.php';

    $sessionHandler = new Session;

    if ($sessionHandler->create($_POST['login'])) {
		localRedir('/');
	}
    else {
        localRedir('/adminv2/user/');
	}
}
else {
    localRedir('/adminv2/user/');
}

?>