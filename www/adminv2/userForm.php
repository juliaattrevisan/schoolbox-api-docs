<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();
$userMapper = \Schoolbox\UserMapper::getInstance();
$passwordGenerator = new \Alaress\Password\Password();
$userHandler = new User();
$groupHandler = new Group();

/* Superusers only
 */
if (!$groupHandler->isSuperuser()) {
    error(19);
}

/* Update existing user details
 */
if (is_numeric($_POST['userID']) && $_POST['modusername']) {
    try {
        $user = $userMapper->findById($_POST['userID']);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(31);
        exit;
    }
    if ($_POST['type'] == 'delete') {
        $userMapper->delete($user);
        SitePage::localRedir('/adminv2/user/');
    } else {
        // Username check?

        $user->setUsername($_POST['modusername']);
        $user->setTitle($_POST['title']);
        $user->setFirstname($_POST['firstname']);
        $user->setLastname($_POST['lastname']);
        $user->setExternalId($_POST['database_id'] == '' ? null : $_POST['database_id']);
        $user->setLocalEmail($_POST['local_email']);
        $user->setUseInternalMail($_POST['mail_handler']);
        $user->setAltEmail($_POST['alt_email']);
        $user->setWebmail($_POST['webmail']);
        $user->getRole()->setId($_POST['group']);

        // Set campi

        $user->setSuperuser(isset($_POST['superuser']));
        $user->setYear($_POST['year'] == '' ? null : $_POST['year']);
        $user->setEportfolioEnable(!empty($_POST['eportfolio']));
        $user->setHideContacts(!empty($_POST['hide_contacts']));
        $user->setHideTimetable(!empty($_POST['hide_timetable']));
        $user->getSchoolHouse()->setId($_POST['house']);
        $user->getResidentialHouse()->setId($_POST['residential_house']);
        $user->setEnabled($_POST['account_enabled']);

//        $user = array(
//            'id' => $_POST['userID'],
//            'username' => $_POST['modusername'],
//            'title' => $_POST['title'],
//            'firstname' => $_POST['firstname'],
//            'lastname' => $_POST['lastname'],
//            'synergy_id' => $_POST['database_id'] == '' ? 'NULL' : $_POST['database_id'],
//            'local_email' => $_POST['local_email'],
//            'mail_handler' => $_POST['mail_handler'],
//            'alt_email' => $_POST['alt_email'],
//            'webmail' => $_POST['webmail'],
//            'group_id' => $_POST['group'],
//            'campus' => $_POST['campus'],
//            'defaultCampus' => $_POST['defaultCampus'],
//            'su' => isset($_POST['superuser']) ? '1' : '0',
//            'year' => $_POST['year'] == '' ? 'NULL' : $_POST['year'],
//            'eportfolio_enabled' => $_POST['eportfolio'] == 1 ? 1 : 0,
//            'hide_contacts' => $_POST['hide_contacts'] == 1 ? 1 : 0,
//            'hide_timetable' => $_POST['hide_timetable'] == 1 ? 1 : 0,
//            'house' => $_POST['house'],
//            'residential_house_id' => $_POST['residential_house'],
//            'enabled' => $_POST['account_enabled']
//        );

        //Do not change password if its the default output value
        if ($_POST['modpassword'] != 'donotchange') {
            $user->setPassword($passwordGenerator->hash($_POST['modpassword']));
//            $user['password'] = $_POST['modpassword'];
//            $user['email_pass'] = $_POST['modpassword'];
        }

        $userMapper->save($user);
        if (!$userMapper->save($user)) {
            error(138);
            exit;
        } else {
            // Save campi and superuser status?
        }

        SitePage::localRedir('/adminv2/modify-user/' . $user->getId());
    }
}
/* Create new user
 */
elseif ($_POST['modusername']) {
    $user = new \Schoolbox\User();
    $user->setUsername($_POST['modusername']);
    $user->setTitle($_POST['title']);
    $user->setFirstname($_POST['firstname']);
    $user->setLastname($_POST['lastname']);
    $user->setExternalId($_POST['database_id'] == '' ? null : $_POST['database_id']);
    $user->setLocalEmail($_POST['local_email']);
    $user->setUseInternalMail($_POST['mail_handler']);
    $user->setAltEmail($_POST['alt_email']);
    $user->setWebmail($_POST['webmail']);
    $user->getRole()->setId($_POST['group']);
    $user->setPassword($_POST['modpassword'] == '' ? null : $passwordGenerator->hash($_POST['modpassword']));

    // Set campi

    $user->setSuperuser(isset($_POST['superuser']));
    $user->setYear($_POST['year'] == '' ? null : $_POST['year']);
    $user->setEportfolioEnable(!empty($_POST['eportfolio']));
    $user->setHideContacts(!empty($_POST['hide_contacts']));
    $user->setHideTimetable(!empty($_POST['hide_timetable']));
    $user->getSchoolHouse()->setId($_POST['house']);
    $user->getResidentialHouse()->setId($_POST['residential_house']);
    $user->setEnabled($_POST['account_enabled']);

//    $user = array(
//        'username' => $_POST['modusername'],
//        'title' => $_POST['title'],
//        'firstname' => $_POST['firstname'],
//        'lastname' => $_POST['lastname'],
//        'synergy_id' => $_POST['database_id'] == '' ? 'NULL' : $_POST['database_id'],
//        'local_email' => $_POST['local_email'],
//        'mail_handler' => $_POST['mail_handler'],
//        'alt_email' => $_POST['alt_email'],
//        'webmail' => $_POST['webmail'],
//        'group_id' => $_POST['group'],
//        'password' => $_POST['modpassword'],
//        'email_pass' => $_POST['modpassword'],
//        'campus' => $_POST['campus'],
//        'defaultCampus' => $_POST['defaultCampus'],
//        'su' => $_POST['superuser'],
//        'year' => $_POST['year'] == '' ? 'NULL' : $_POST['year'],
//        'eportfolio_enabled' => $_POST['eportfolio'] == 1 ? 1 : 0,
//        'hide_contacts' => $_POST['hide_contacts'] == 1 ? 1 : 0,
//        'house' => $_POST['house'],
//        'residential_house_id' => $_POST['residential_house'],
//        'enabled' => true,
//    );

    /* Users require a campus array before they can be created successfully
	 */
    if (is_array($_POST['campus']) && count($_POST['campus']) || $_POST['defaultCampus']) {
//        $user['campus'] = $user['campus'] ?: [$user['defaultCampus']];
//        $id = $userHandler->create($user);

        $userMapper->save($user);

        if (!$user->getId()) {
            error(138);
        }
    } else {
        error(158);
    }

    SitePage::localRedir('/adminv2/modify-user/' . $id);
} /* Add user to group
 */
elseif (isset($_POST['groupSubmit']) && is_numeric($_POST['uid'])) {
    $groupHandler->setService(Scrub::text($_POST['service']));
    $groupHandler->addUser(Scrub::text($_POST['sid']), Scrub::text($_POST['uid']), Scrub::text($_POST['type']));

    SitePage::localRedir('/adminv2/modify-user/' . Scrub::text($_POST['uid']) . '#permissions');
} /* Add user to service
 */
elseif (isset($_POST['addService']) && is_numeric($_POST['uid'])) {
    $groupHandler->setService(Scrub::text($_POST['service']));
    $groupHandler->addUser(Scrub::text($_POST['sid']), Scrub::text($_POST['uid']), Scrub::text($_POST['type']));

    SitePage::localRedir('/adminv2/modify-user/' . Scrub::text($_POST['uid'] . '#permissions'));
} /* Ajax Update Groups and their access permissions etc
 */
elseif ($_POST['updateGroups'] == true) {
    $groupHandler->setService(Scrub::text($_POST['service']));

    /* If assigning a new admin, demote any existing owners
	 */
    if (Scrub::text($_POST['type']) == FULL_ACCESS) {
        $userAccessMapper = new \Schoolbox\Permission\Access\UserAccessMapper();
        $userAccess = $userAccessMapper->findOneByColumn('sid', $_POST['sid']);

        $groupHandler->setUserPermission($_POST['sid'], $userAccess->getUser()->getId(), WRITE_ACCESS);
    }

    /* Update user access as normal
	 */
    $groupHandler->setUserGroupType(Scrub::text($_POST['sid']), Scrub::text($_POST['uid']), Scrub::text($_POST['type']));
} /* Ajax Update Service Permissions (sid corresponds to campus)
 */
elseif (Scrub::text($_POST['updateServices']) == true) {
    $groupHandler->setService(Scrub::text($_POST['service']));
    $groupHandler->setUserGroupType(Scrub::text($_POST['sid']), Scrub::text($_POST['uid']), Scrub::text($_POST['type']));
} /* Ajax Update SID values
 */
elseif (Scrub::text($_POST['updateSID']) == true) {
    $groupHandler->setService(Scrub::text($_POST['service']));
    $groupHandler->setSID(Scrub::text($_POST['previousSid']), Scrub::text($_POST['sid']), Scrub::text($_POST['uid']));
} /* Remove a service
 */
elseif (is_numeric($_GET['deleteService']) && is_numeric($_GET['sid']) && is_numeric($_GET['uid'])) {
    $groupHandler->setService(Scrub::text($_GET['deleteService']));
    $groupHandler->deleteUser(Scrub::text($_GET['sid']), Scrub::text($_GET['uid']));

    SitePage::localRedir('/adminv2/modify-user/' . Scrub::text($_GET['uid']) . '#permissions');
}
elseif (is_numeric($_GET['deleteUserLink']) && is_numeric($_GET['linkedUser']) ) {
    /* Deletes linked users */

    try {
        $originalUser = \Schoolbox\UserMapper::getInstance()->findById($_GET['deleteUserLink']);
    }
    catch(\Schoolbox\Mapper\Exception $e)  {
        error(2);
    }
    $linkedUserList = new \Schoolbox\Collection;
    foreach($originalUser->getLinkedUser() as $linkedUser) {
        if($linkedUser->getId() != $_GET['linkedUser']) {
            $linkedUserList->add($linkedUser);
        }
    }
    $originalUser->setLinkedUser($linkedUserList);

    \Schoolbox\UserMapper::getInstance()->save($originalUser);

    SitePage::localRedir('/adminv2/modify-user/'.$originalUser->getId().'#linked');
}
elseif (is_array($_POST['linkUser'])) {
    /* Adds linked users */

    try {
        $originalUser = \Schoolbox\UserMapper::getInstance()->findById($_POST['userID']);
    }
    catch(\Schoolbox\Mapper\Exception $e)  {
        error(2);
    }

    foreach($_POST['linkUser'] as $userId ){
        try{
            $linkedUser = \Schoolbox\UserMapper::getInstance()->findById($userId);

            $originalUser->getLinkedUser()->add($linkedUser);
        } catch (\Schoolbox\Mapper\Exception $e) {
            //noop on user not found
        }
    }

    \Schoolbox\UserMapper::getInstance()->save($originalUser);

    SitePage::localRedir('/adminv2/modify-user/'.$originalUser->getId().'#linked');
}
/* Switch logins to the current user being viewed
 */
elseif ($_POST['login']) {
    $oldSession = $_SESSION;

    $sessionHandler = new Session();

    if ($sessionHandler->create($_POST['login'])) {
/* Logging
		 */
        $_SESSION['previous'] = $oldSession;

        $logger = new User_Logger_AssumeUser($oldSession['user'], $_SESSION['user']);
        $logger->notify();

        SitePage::localRedir('/');
    } else {
        SitePage::localRedir('/adminv2/user/');
    }
} else {
    SitePage::localRedir('/adminv2/user/');
}
