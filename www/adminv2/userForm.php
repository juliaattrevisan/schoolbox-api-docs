<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once SITE_root . '/library/group.php';
require_once SITE_root . '/library/category.php';
require_once SITE_root . '/library/user.php';

$page = new SitePage;
$userHandler = new User;
$groupHandler = new Group;
$categoryHandler = new Category;

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

/* Add user to service
 */
else if (($_POST['serviceSubmit'] == 'Add') && $_POST['uid']) {
    $groupHandler->setService($_POST['service']);
    $groupHandler->addUser($_POST['sid'], $_POST['uid'], $_POST['type']);

	localRedir('/adminv2/modify-user/'.$_POST['uid']);
}

/* Update existing user details
 */
else if(is_numeric($_POST['userID']) && $_POST['modusername'] ) {
    if ($_POST['delete']) {
        $userHandler->delete($_POST['userID']);

        localRedir('/adminv2/user/');
    }

    $user = array(
		'id'				 => $_POST['userID'],
		'username'			 => $_POST['modusername'],
		'title'				 => $_POST['title'],
		'firstname'			 => $_POST['firstname'],
		'lastname'			 => $_POST['lastname'],
		'synergy_id'		 => $_POST['database_id']==''?'NULL':$_POST['database_id'],
		'local_email'		 => $_POST['local_email'],
		'alt_email'			 => $_POST['alt_email'],
		'group_id'			 => $_POST['group'],
        'campus'			 => $_POST['campus'],
        'defaultCampus'	     => $_POST['defaultCampus'],
		'su'				 => $_POST['superuser'],
		'year'				 => $_POST['year']==''?'NULL':$_POST['year'],
		'eportfolio_enabled' => $_POST['eportfolio']==1?1:0
    );

    //Do not change password if its the default output value
    if($_POST['modpassword'] != 'donotchange')
        $user['password'] = $_POST['modpassword'];

    $userHandler->update($user);

    localRedir('/adminv2/modify-user/'.$user['id']);
}

/* Create new user
 */
else if ($_POST['modusername']) {
    $user = array(
		'username'			 => $_POST['modusername'],
		'title'				 => $_POST['title'],
		'firstname'			 => $_POST['firstname'],
		'lastname'			 => $_POST['lastname'],
		'synergy_id'		 => $_POST['database_id']==''?'NULL':$_POST['database_id'],
		'local_email'		 => $_POST['local_email'],
		'alt_email'			 => $_POST['alt_email'],
		'group_id'			 => $_POST['group'],
		'password'			 => $_POST['modpassword'],
		'campus'			 => $_POST['campus'],
		'su'			 	 => $_POST['superuser'],
		'year'		 		 => $_POST['year']==''?'NULL':$_POST['year'],
		'eportfolio_enabled' => $_POST['eportfolio']==1?1:0
    );

    $id = $userHandler->create($user);

	localRedir('/adminv2/modify-user/'.$id);
}

/* Ajax Update Groups and their access permissions etc
 */
else if ($_POST['updateGroups'] == true) {
    $groupHandler->setService($_POST['service']);

	/* If assigning a new admin, demote any existing owners
	 */
	if ($_POST['type'] == FULL_ACCESS) {
		$category = $categoryHandler->getByID($_POST['sid']);
		$groupHandler->setUserPermission($_POST['sid'], $category['owner'], WRITE_ACCESS);
	}

	/* Update user access as normal
	 */
	$groupHandler->setUserGroupType($_POST['sid'], $_POST['uid'], $_POST['type']);
}

/* Ajax Update Service Permissions (sid corresponds to campus)
 */
else if ($_POST['updateServices'] == true) {
    $groupHandler->setService($_POST['service']);
	$groupHandler->setUserGroupType($_POST['sid'], $_POST['uid'], $_POST['type']);
}

/* Ajax Update SID values
 */
else if ($_POST['updateSID'] == true) {
	$groupHandler->setService($_POST['service']);
	$groupHandler->setSID($_POST['previousSid'], $_POST['sid'], $_POST['uid']);
}

/* Remove a service
 */
else if (is_numeric($_GET['deleteService']) && is_numeric($_GET['sid']) && is_numeric($_GET['uid'])) {
	$groupHandler->setService($_GET['deleteService']);
	$groupHandler->deleteUser($_GET['sid'], $_GET['uid']);

	localRedir('/adminv2/modify-user/'.$_GET['uid']);
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