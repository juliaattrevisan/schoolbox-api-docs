<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once SITE_root . '/library/group.php';
require_once SITE_root . '/library/category.php';
require_once SITE_root . '/library/user.php';

$page = new SitePage;
$userHandler = new User;
$groupHandler = new Group;
$categoryHandler = new Category;

/* Superusers only 
 */
if (!$groupHandler->isSuperuser()) {
    error(19);
}

/* Update existing user details
 */
if (is_numeric($_POST['userID']) && $_POST['modusername'] ) {
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
    if($_POST['modpassword'] != 'donotchange') {
        $user['password'] = $_POST['modpassword'];
        $user['email_pass'] = $_POST['modpassword'];
    }

    if ($userHandler->update($user) === false) {
		error(138);
	}

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
        'email_pass'	     => $_POST['modpassword'],
		'campus'			 => $_POST['campus'],
        'defaultCampus'	     => $_POST['defaultCampus'],
		'su'			 	 => $_POST['superuser'],
		'year'		 		 => $_POST['year']==''?'NULL':$_POST['year'],
		'eportfolio_enabled' => $_POST['eportfolio']==1?1:0
    );

    $id = $userHandler->create($user);

	if (!$id) {
		error(138);
	}

	localRedir('/adminv2/modify-user/'.$id);
}

/* Add user to group
 */
else if (isset($_POST['groupSubmit']) && is_numeric($_POST['uid'])) {
    $groupHandler->setService(scrubTEXT($_POST['service']));
    $groupHandler->addUser(scrubTEXT($_POST['sid']), scrubTEXT($_POST['uid']), scrubTEXT($_POST['type']));

	localRedir('/adminv2/modify-user/' . scrubTEXT($_POST['uid']) . '#permissions');
}

/* Add user to service
 */
else if (isset($_POST['addService']) && is_numeric($_POST['uid'])) {
    $groupHandler->setService(scrubTEXT($_POST['service']));
    $groupHandler->addUser(scrubTEXT($_POST['sid']), scrubTEXT($_POST['uid']), scrubTEXT($_POST['type']));

	localRedir('/adminv2/modify-user/' . scrubTEXT($_POST['uid'] . '#permissions'));
}

/* Ajax Update Groups and their access permissions etc
 */
else if ($_POST['updateGroups'] == true) {
    $groupHandler->setService(scrubTEXT($_POST['service']));

	/* If assigning a new admin, demote any existing owners
	 */
	if (scrubTEXT($_POST['type']) == FULL_ACCESS) {
		$category = $categoryHandler->getByID(scrubTEXT($_POST['sid']));
		$groupHandler->setUserPermission(scrubTEXT($_POST['sid']), $category['owner'], WRITE_ACCESS);
	}

	/* Update user access as normal
	 */
	$groupHandler->setUserGroupType(scrubTEXT($_POST['sid']), scrubTEXT($_POST['uid']), scrubTEXT($_POST['type']));
}

/* Ajax Update Service Permissions (sid corresponds to campus)
 */
else if (scrubTEXT($_POST['updateServices']) == true) {
    $groupHandler->setService(scrubTEXT($_POST['service']));
	$groupHandler->setUserGroupType(scrubTEXT($_POST['sid']), scrubTEXT($_POST['uid']), scrubTEXT($_POST['type']));
}

/* Ajax Update SID values
 */
else if (scrubTEXT($_POST['updateSID']) == true) {
	$groupHandler->setService(scrubTEXT($_POST['service']));
	$groupHandler->setSID(scrubTEXT($_POST['previousSid']), scrubTEXT($_POST['sid']), scrubTEXT($_POST['uid']));
}

/* Remove a service
 */
else if (is_numeric($_GET['deleteService']) && is_numeric($_GET['sid']) && is_numeric($_GET['uid'])) {
	$groupHandler->setService(scrubTEXT($_GET['deleteService']));
	$groupHandler->deleteUser(scrubTEXT($_GET['sid']), scrubTEXT($_GET['uid']));

	localRedir('/adminv2/modify-user/' . scrubTEXT($_GET['uid'])  . '#permissions');
}

/* Switch logins to the current user being viewed
 */
else if ($_POST['login']) {
    require_once SITE_root . '/include/Session.php';

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