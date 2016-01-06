<?php
require_once(dirname(__FILE__) . '/../../library/Bootstrap.php');

$page            = new SitePage();
$userMapper      = \Schoolbox\UserMapper::getInstance();
$passwordService = new \Alaress\Password\Service();
$groupHandler    = new Group();

/* Superusers only
 */
if (!$_SESSION['user']->isSuperuser()) {
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
        // Username check - if any other user has this username, abort
        foreach ($userMapper->findByColumn('username', $_POST['modusername']) as $userWithUsername) {
            if ($userWithUsername->getId() !=  $user->getId()) {
                error(138);
                exit;
            }
        }

        $user->setTitle($_POST['title']);
        $user->setFirstname($_POST['firstname']);
        $user->setLastname($_POST['lastname']);
        $user->setExternalId($_POST['database_id'] == '' ? null : $_POST['database_id']);
        $user->getRole()->setId($_POST['group']);
        $user->setCampusCollection(new \Schoolbox\CampusCollection());
        foreach ($_POST['campus'] as $campusId) {
            /** @var \Schoolbox\Campus $campus */
            $campus = new \Schoolbox\Campus();
            $campus->setId($campusId);
            if ($campusId == $_POST['defaultCampus']) {
                $user->setDefaultCampus($campus);
            }
            $user->getCampusCollection()->add($campus);
        }
        $user->setUsername($_POST['modusername']);

        // Do not change password if its the default output value
        if ($_POST['modpassword'] != 'donotchange') {
            $user
                ->setRetrievablePassword($_POST['modpassword'])
                ->setPassword($passwordService->hash($_POST['modpassword']));
        }
        $user->setAltEmail($_POST['alt_email']);
        $user->setYear($_POST['year'] == '' ? null : $_POST['year']);
        $user->getSchoolHouse()->setId($_POST['house']);
        $user->getResidentialHouse()->setId($_POST['residential_house']);

        // Checkboxes of value='1' (NULL if unchecked)
        $user->setEportfolioEnable(!empty($_POST['eportfolio']));
        $user->setHideContacts(!empty($_POST['hide_contacts']));
        $user->setHideTimetable(!empty($_POST['hide_timetable']));
        $user->setLocalEmail(!empty($_POST['local_email']));
        $user->setUseInternalMail(!empty($_POST['mail_handler']));
        $user->setWebmail(!empty($_POST['webmail']));
        $user->setSuperuser(isset($_POST['superuser']));
        $user->setEnabled(!empty($_POST['account_enabled']));
        $user->setPositionTitle($_POST['position_title']);

        $userMapper->save($user);
        SitePage::localRedir('/adminv2/modify-user/' . $user->getId());
    }
}
/* Create new user
 */
elseif ($_POST['modusername']) {
    // Username check - if any other user has this username, abort
    if ($userMapper->countByColumn('username', $_POST['modusername']) > 0) {
        error(138);
        exit;
    }

    $user = new \Schoolbox\User();
    $user->setUsername($_POST['modusername']);
    $user->setTitle($_POST['title']);
    $user->setFirstname($_POST['firstname']);
    $user->setLastname($_POST['lastname']);
    $user->setExternalId($_POST['database_id'] == '' ? null : $_POST['database_id']);
    $user->setAltEmail($_POST['alt_email']);
    $user->setPositionTitle($_POST['position_title']);
    $user->setWebmail($_POST['webmail']);
    $user->getRole()->setId($_POST['group']);
    foreach ($_POST['campus'] as $campusId) {
        /** @var \Schoolbox\Campus $campus */
        $campus = new \Schoolbox\Campus();
        $campus->setId($campusId);
        if ($campusId == $_POST['defaultCampus']) {
            $user->setDefaultCampus($campus);
        }
        $user->getCampusCollection()->add($campus);
    }
    $user->setRetrievablePassword($_POST['modpassword'] == '' ? null : $_POST['modpassword']);
    $user->setPassword($_POST['modpassword'] == '' ? null : $passwordService->hash($_POST['modpassword']));
    $user->setSuperuser(isset($_POST['superuser']));
    $user->setYear($_POST['year'] == '' ? null : $_POST['year']);
    $user->setEportfolioEnable(!empty($_POST['eportfolio']));
    $user->setHideContacts(!empty($_POST['hide_contacts']));
    $user->setHideTimetable(!empty($_POST['hide_timetable']));
    $user->setLocalEmail(!empty($_POST['local_email']));
    $user->setUseInternalMail(!empty($_POST['mail_handler']));
    $user->setWebmail(!empty($_POST['webmail']));
    $user->getSchoolHouse()->setId($_POST['house']);
    $user->getResidentialHouse()->setId($_POST['residential_house']);
    $user->setEnabled(isset($_POST['account_enabled']) ? $_POST['account_enabled'] : true);

    /* Users require a campus array before they can be created successfully
     */
    if (is_array($_POST['campus']) && count($_POST['campus']) || $_POST['defaultCampus']) {
        $userMapper->save($user);
    } else {
        error(158);
    }

    SitePage::localRedir('/adminv2/modify-user/' . $user->getId());
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
