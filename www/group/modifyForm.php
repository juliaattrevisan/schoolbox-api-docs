<?php
use Schoolbox\Collection;
use Schoolbox\Realtime\Message\RerouteOverride;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\User;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\UserMapper;

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();
$groupHandler = new Group(HOMEPAGE_SERVICE);

$uaMapper = UserAccessMapper::getInstance();
$ua = new UserAccess();
$ua->setService(UserAccess::SERVICE_HOMEPAGE);

$catMapper = CategoryMapper::getInstance();
$userMapper = UserMapper::getInstance();
$groupMapper = GroupMapper::getInstance();

$categoryId = $_REQUEST['categoryID'];

if(!is_numeric($categoryId)) {
	error(2);
}

/* Check access */
if($groupHandler->getPermission($categoryId) < FULL_ACCESS) {
	error(19);
}

if (is_numeric($_POST['type']) || is_array($_POST['msg'])) {
    /** @var \Schoolbox\Resource\Group $group */
    try {
        $group = $groupMapper->findOneByColumn('cid', $categoryId);
    } catch (\Schoolbox\Mapper\Exception $e) {
        $group = $groupMapper->createModel();
        // yes, group category doesn't map to an actual category.
        // no, I'm not fixing it right now, but will at least check it exists.
        try {
            $catMapper->findById($categoryId);
            $group->setCategory($categoryId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            header(':', true, 404);
            die("The specified folder ({$categoryId}) does not exist.");
        }
    }

    if (isset($_POST['type'])) {
        $group->setType($_POST['type']);
    }

    if (isset($_POST['msg'])) {

        $oMapper = RerouteOverrideMapper::getInstance();

        // prime the global suppressor!
        $override = new RerouteOverride();
        $override->setUser($userMapper->createModel()->setId(0)); // ID 0 = global
        $override->setRouteKey('service.1.' . $group->getCategory());
        $override->setOutgoingLevel(0);

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
        }
        else {
            // insert the global suppressor
            $oMapper->save($override);

            // force follow being active when the group is muted makes no sense
            $group->setForceFollow(false);
        }
    }

    $groupMapper->save($group);
//    header(':', true, $_POST['debug'] ? 200 : 204);
    header(':', true, 204);
    exit;
}

/* Change write permission */
elseif(is_numeric($_GET['write']) && is_numeric($_GET['userID'])) {
	$ua->setServiceIdentifier($_GET['categoryID'])
	   ->setUser($userMapper->findById($_GET['userID']))
	   ->setAccessLevel($_GET['write']);
	$uaMapper->save($ua);
}

/* Delete user */
elseif(isset($_REQUEST['delete'])) {
	if(isset($_REQUEST['service'])) {
		$groupHandler->setService($_REQUEST['service']);
	}

	foreach((array)$_REQUEST['delete'] as $id) {
		if(is_numeric($id)) {
			$groupHandler->deleteUser($categoryId, $id);
		}
	}

	$page->backToTheFuture(1);
}

/* Accept user */
elseif(isset($_REQUEST['accept'])) {
	foreach((array)$_REQUEST['accept'] as $id) {
		if (is_numeric($id)) {
			$ua->setAccessLevel(UserAccess::ACCESS_READ)
			   ->setUser($userMapper->findById($id))
			   ->setServiceIdentifier($categoryId);
			$uaMapper->save($ua);
		}
	}

	SitePage::localRedir('/group/modify/' . $categoryId);
}

/* Add user */
elseif(isset($_POST['userID'])) {
	$ua->setServiceIdentifier($_GET['categoryID']);
    $folder = $catMapper->findById($_GET['categoryID']);
	/** @var Collection|User[] $userList */
	$userList = $userMapper->findByColumn('id', $_POST['userID']);

	foreach ($userList as $userObject) {
        /* Do not demote existing owner */
        if(!$uaMapper->isFolderOwner($userObject, $folder)) {
            $ua->setUser($userObject)
               ->setAccessLevel(UserAccess::ACCESS_READ);
            $uaMapper->save($ua);
        }
	}
	SitePage::localRedir('/group/modify/' . $_GET['categoryID']);
}

/* Set new owner/administrator */
elseif(is_numeric($_GET['owner'])) {
	try {
		$category = $catMapper->findById($categoryId);
	}
	catch(Schoolbox\Mapper\Exception $e) {
		error(2);
		exit;
	}

	//check if member is allowed to become a owner
	if (!$groupHandler->allowOwner($_GET['owner'])) {
		error(30);
	}

	//if we are accessing group for first time create group
	if (!$groupHandler->groupExists($_GET['categoryID'])) {
		$groupHandler->createGroup($_GET['categoryID']);
	}

	// demote current owner
	try {
		/** @var UserAccess $ua */
		$ua = $uaMapper->findOneByColumnList(array(
			'service' => UserAccess::SERVICE_HOMEPAGE,
			'sid'     => $category->getId(),
			'type'    => UserAccess::ACCESS_FULL
		));

		$ua->setAccessLevel(UserAccess::ACCESS_WRITE);
		$uaMapper->save($ua);
	}
	catch(Schoolbox\Mapper\Exception $e) {
		// just ignore it, this kingdom had no king
		$ua->setUser(new User())
		   ->setServiceIdentifier($category->getId());
	}

	// promote new owner
	$ua->setAccessLevel(UserAccess::ACCESS_FULL)
	   ->getUser()->setId($_GET['owner']);

	$uaMapper->save($ua);

	// re-check permissions, redirect user changing ownership to homepage if they have lost ownership
	$groupHandler->clearPermissionsCache();
	if ($groupHandler->getPermission($_GET['categoryID']) < FULL_ACCESS) {
		SitePage::localRedir("/homepage/" . $_GET['categoryID']);
	}
	else {
		SitePage::localRedir("/group/modify/" . $_GET['categoryID']);
	}
}

/* Disband group */
elseif(isset($_GET['disband'])) {
	$groupHandler->disbandGroup($_GET['categoryID']);
	SitePage::localRedir('/');
}

/* Bulk-add users */
elseif(isset($_POST['bulkAdd'])) {
	//get rid of newlines
	$string = preg_replace('/(\r\n|\n|\r)/', ',', $_POST['bulkAdd']);

	//split the list into an array
	$list = preg_split('/[,; ]/', $string);

	$ua->setServiceIdentifier($categoryId)
	   ->setAccessLevel(UserAccess::ACCESS_READ);

	foreach($list as $key) {
		$key = trim($key);

		if(!empty($key)) {
			try {
				if(is_numeric($key)) {
					$user = $userMapper->findOneByColumn('synergy_id', $key);
				}
				elseif(is_string($key)) {
					$user = $userMapper->findOneByColumn('username', $key);
				}
				else {
					continue;
				}
			}
			catch(Schoolbox\Mapper\Exception $e) {
				continue;
			}

			$ua->setUser($user);
			$uaMapper->save($ua);
		}
	}

	SitePage::localRedir('/group/modify/' . $categoryId);
}

