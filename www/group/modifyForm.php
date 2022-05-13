<?php

use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Collection;
use Schoolbox\Realtime\Message\RerouteOverride;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\User;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();
$groupHandler = new Group(UserAccess::SERVICE_HOMEPAGE);

$uaMapper = UserAccessMapper::getInstance();
$ua = new UserAccess();
$ua->setService(UserAccess::SERVICE_HOMEPAGE);
$ua->setFavourite(false);

$catMapper = FolderMapper::getInstance();
$userMapper = UserMapper::getInstance();
$groupMapper = GroupMapper::getInstance();

$categoryId = $_REQUEST['categoryID'];
$currentUser = $userMapper->findById($_SESSION["id"]);

try {
    $folder = $catMapper->findById($categoryId);
} catch (\Schoolbox\Mapper\Exception $e) {
    error(2);
    exit;
}

if (!is_numeric($categoryId)) {
    error(2);
}

// This variable will updated later; set it to the least permission as a default value
$currentUserPermission = UserAccess::ACCESS_DENY;

/* Check access */
if ($groupHandler->getPermission($categoryId) < UserAccess::ACCESS_FULL) {
    error(19);
} else {
    // By setting this manually, we're avoiding cache issues
    // that could result from retrieving the permission from the database.
    $currentUserPermission = UserAccess::ACCESS_FULL;
}

// Handle disbandment before anything else
if (isset($_GET['disband'])) {
    $groupMapper->deleteByColumn('cid', $categoryId);
    SitePage::localRedir('/');
}

if (is_numeric($_POST['type'] ?? false) || is_array($_POST['msg'] ?? false)) {
    /** @var \Schoolbox\Resource\Group $group */
    try {
        $group = $groupMapper->findOneByColumn('cid', $categoryId);
    } catch (\Schoolbox\Mapper\Exception $e) {
        $group = $groupMapper->createModel();
        // yes, group category doesn't map to an actual category.
        // no, I'm not fixing it right now, but will at least check it exists.
        $folder = $catMapper->findById($categoryId);
        $group->setFolder($folder);
//        try {
//            $catMapper->findById($categoryId);
//            $group->setCategory($categoryId);
//        } catch (\Schoolbox\Mapper\Exception $e) {
//            http_response_code(404);
//            die("The specified folder ({$categoryId}) does not exist.");
//        }
    }

    if (isset($_POST['type'])) {
        $group->setType($_POST['type']);
    }

    if (isset($_POST['msg'])) {
        $oMapper = RerouteOverrideMapper::getInstance();

        // prime the global suppressor!
        $override = new RerouteOverride();
        $override->setUser($userMapper->createModel()->setId(0)); // ID 0 = global
        $override->setRouteKey('service.1.' . $group->getFolder()->getId());
        $override->setDelivery([ 'MU' ]);

        if ($_POST['msg']['enabled']) {
            $force  = !!$_POST['msg']['force_follow'];
            $search = $oMapper->dehydrate($override);

            // set force-follow based on what we got
            $group->setForceFollow($force);

            // remove individual mutes as well
            if ($force) {
                unset($search['user_id']);
            }

            // remove the global suppressor
            $oMapper->deleteByColumnList($search);
        } else {
            // insert the global suppressor
            $oMapper->save($override);

            // force follow being active when the group is muted makes no sense
            $group->setForceFollow(false);
        }
    }

    $groupMapper->save($group);
}

/* Change write permission - no longer done via link, always done via form */
//elseif(is_numeric($_GET['write']) && is_numeric($_GET['userID'])) {
//  $ua->setServiceIdentifier($_GET['categoryID'])
//     ->setUser($userMapper->findById($_GET['userID']))
//     ->setAccessLevel($_GET['write']);
//  $uaMapper->save($ua);
//}

/* Delete user */
if (isset($_REQUEST['delete'])) {
    if (isset($_REQUEST['service'])) {
        $groupHandler->setService($_REQUEST['service']);
    }

    foreach ((array)$_REQUEST['delete'] as $id) {
        if (is_numeric($id)) {
            $groupHandler->deleteUser($categoryId, $id);
        }
    }
}

/* Accept/reject user */
if (isset($_REQUEST['accept'])) {
    foreach ((array)$_REQUEST['accept'] as $id) {
        if (is_numeric($id)) {
            $ua->setAccessLevel(UserAccess::ACCESS_READ)
               ->setUser($userMapper->findById($id))
               ->setServiceIdentifier($categoryId);
            $uaMapper->save($ua);
        }
    }
}
if (isset($_REQUEST['reject'])) {
    foreach ((array)$_REQUEST['reject'] as $id) {
        if (is_numeric($id)) {
            $uaMapper->deleteByColumnList([
                'service' => UserAccess::SERVICE_HOMEPAGE,
                'uid'     => $id,
                'sid'     => $categoryId,
            ]);
        }
    }
}

/* Add user */
if (isset($_POST['userID'])) {
    $ua->setServiceIdentifier($categoryId);
    /** @var Collection|User[] $userList */
    $userList = $userMapper->findByColumn('id', $_POST['userID']);
    $memberList = $uaMapper->findUsersInCategory($folder, null, UserAccess::ACCESS_READ, false);

    // Do not attempt to add existing members
    $newMemberList = $userList->diff($memberList);

    // Check whether userGroup already exists and create one if not
    try {
        /** @var \Schoolbox\Resource\Group $group */
        $group = $groupMapper->findOneByColumn('cid', $categoryId);
    } catch (\Schoolbox\Mapper\Exception $e) {
        $folder = $catMapper->findById($categoryId);
        $group  = $groupMapper->createModel()
                              ->setFolder($folder)
                              ->setType(\Schoolbox\Resource\Group::PRIVATE_GROUP)
                              ->setForceFollow(false);
        $groupMapper->save($group);
    }

    foreach ($newMemberList as $userObject) {
        $ua->setUser($userObject)
            ->setAccessLevel(UserAccess::ACCESS_READ);
        $uaMapper->save($ua);
    }
}

// Change membership parameters
if (!empty($_POST['accessLevel'])) {
    foreach ($_POST['accessLevel'] as $userId => $accessLevel) {
        /* Set new owner/administrator */
        $user = new User();
        $user->setId($userId);
        //find the actual userAccess object, not just create a new one and blow away the old object...
        $ua = $uaMapper->findOneByColumnList([
                   'uid' => $user->getId(),
                   'sid' => $categoryId,
               ]);
        if ($accessLevel == UserAccess::ACCESS_FULL) {
            //check if member is allowed to become a owner
            if (!$groupHandler->allowOwner($userId)) {
                error(30);
            }

            //if we are accessing group for first time create group
            if (!$groupHandler->groupExists($categoryId)) {
                $groupHandler->createGroup($categoryId);
            }

            // promote new owner
            $ua->setAccessLevel($accessLevel)
                ->setUser($user)
                ->setServiceIdentifier($folder->getId())
                ->setService(UserAccess::SERVICE_HOMEPAGE);

            $uaMapper->save($ua);
        } elseif ($accessLevel == UserAccess::ACCESS_DENY) {
            $uaMapper->deleteByColumnList([
                'service' => UserAccess::SERVICE_HOMEPAGE,
                'uid'     => $userId,
                'sid'     => $categoryId,
            ]);
        } else {
            $ua->setServiceIdentifier($folder->getId())
                ->setAccessLevel($accessLevel)
                ->setUser($user)
                ->setService(UserAccess::SERVICE_HOMEPAGE);

            $uaMapper->save($ua);
        }

        if ($user->getId() == $_SESSION['user']->getId()) {
            $currentUserPermission = $accessLevel;
        }
    }
}

/* Bulk-add users */
if (isset($_POST['bulkAdd'])) {
    //get rid of newlines
    $string = preg_replace('/(\r\n|\n|\r)/', ',', $_POST['bulkAdd']);

    //split the list into an array
    $list = preg_split('/[,;]/', $string);

    $ua->setServiceIdentifier($categoryId)
       ->setAccessLevel(UserAccess::ACCESS_READ);

    foreach ($list as $key) {
        $key = trim($key);

        if (!empty($key)) {
            try {
                if (is_numeric($key)) {
                    $user = $userMapper->findByExternalId($key);
                } elseif (is_string($key)) {
                    // First assume it is non-numeric external id
                    try {
                        $user = $userMapper->findByExternalId($key);
                    } catch (\Schoolbox\Mapper\Exception $e) {
                        // Then assume it is a username
                        $user = $userMapper->findOneByColumn('username', $key);
                    }
                } else {
                    continue;
                }
            } catch (Schoolbox\Mapper\Exception $e) {
                continue;
            }

            $ua->setUser($user);
            $uaMapper->save($ua);
        }
    }
}

if ($currentUserPermission < UserAccess::ACCESS_FULL && !$_SESSION['su']) {
    // If the user has less then full permission they can no longer stay on the modify group page

    // Check if they came here via AJAX

    if (SitePage::detectAjax()) {
        // Send a JSON reply with the redirect details

        header('Content-type: application/json');

        echo json_encode([
            "action" => "redirect",
            "url" => "/homepage/{$categoryId}"
        ]);
    } else {
        // Otherwise, just do a normal redirect

        SitePage::localRedir("/homepage/" . $categoryId);
    }
} else {
    // Reload the modify page so we can update the DOM

    SitePage::localRedir('/group/modify/' . $categoryId);
}
