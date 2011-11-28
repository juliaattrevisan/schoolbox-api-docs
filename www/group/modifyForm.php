<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();
$groupHandler = new Group(HOMEPAGE_SERVICE);
$categoryHandler = new Category();

if (!is_numeric($_REQUEST['categoryID'])) {
	error(2);
}

/* Check access
 */
if ($groupHandler->getPermission($_REQUEST['categoryID']) < FULL_ACCESS) {
	error(19);
}

/* Change group type
 */
if (is_numeric($_GET['type'])) {
	$groupHandler->setType($_GET['categoryID'], $_GET['type']);
}
/* Change write permission
 */
else if (is_numeric($_GET['write']) && is_numeric($_GET['userID'])) {
	$groupHandler->setUserPermission($_GET['categoryID'], $_GET['userID'], $_GET['write']);
}
/* Delete user
 */
elseif (isset($_REQUEST['delete'])) {
	if (!is_array($_REQUEST['delete'])) {
		$_REQUEST['delete'] = array($_REQUEST['delete']);
	}

	if (isset($_REQUEST['service'])) {
		$groupHandler = new Group($_REQUEST['service']);
	}

	$total = count($_REQUEST['delete']);

	for ($i = 0; $i < $total; $i++) {
		if (is_numeric($_REQUEST['delete'][$i])) {
			$groupHandler->deleteUser($_REQUEST['categoryID'], $_REQUEST['delete'][$i]);
		}
	}

	$page->backToTheFuture(1);
}
/* Accept user
 */
else if (isset($_REQUEST['accept'])) {
	if (!is_array($_REQUEST['accept'])) {
		$_REQUEST['accept'] = array($_REQUEST['accept']);
	}

	$total = count($_REQUEST['accept']);

	for ($i = 0; $i < $total; $i++) {
		if (is_numeric($_REQUEST['accept'][$i])) {
			$groupHandler->setUserPermission($_REQUEST['categoryID'], $_REQUEST['accept'][$i], READ_ACCESS);
		}
	}

	SitePage::localRedir('/group/modify/' . $_REQUEST['categoryID']);
}
/* Add user
 */
else if (isset($_POST['userID'])) {
	$userHandler = new User();
	$user = $userHandler->getByID($_POST['userID']);

	if (isset($user['uid'])) {
		$groupHandler->addUser($_GET['categoryID'], $user['uid']);
	}

	SitePage::localRedir('/group/modify/' . $_GET['categoryID']);
}
else if (is_numeric($_GET['owner'])) {
	$category = $categoryHandler->getByID($_REQUEST['categoryID']);

	if (!isset($category['id'])) {
		error(2);
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
	if (is_numeric($category['owner'])) {
		$groupHandler->setUserPermission($_GET['categoryID'], $category['owner'], WRITE_ACCESS);
	}

	// promote new owner
	$groupHandler->setUserPermission($_GET['categoryID'], $_GET['owner'], FULL_ACCESS);

	// re-check permissions, redirect user changing ownership to homepage if they have lost ownership
	if ($groupHandler->getPermission($_GET['categoryID']) < FULL_ACCESS) {
		SitePage::localRedir("/homepage/" . $_GET['categoryID']);
	}
	else {
		SitePage::localRedir("/group/modify/" . $_GET['categoryID']);
	}
}
else if (isset($_GET['disband'])) {
	$groupHandler->disbandGroup($_GET['categoryID']);
	SitePage::localRedir('/');
}
else if (isset($_POST['bulkAdd'])) {
	$userHandler = new User();

	//get rid of newlines
	$string = preg_replace('/(\r\n|\n|\r)/', ',', $_POST['bulkAdd']);

	//split the list into an array
	$list = preg_split('/[,; ]/', $string);

	foreach ($list as $key) {
		$key = trim($key);

		if (!empty($key)) {
			if (is_numeric($key)) {
				$user = $userHandler->getBySynergy($key);
			}
			else if (is_string($key)) {
				$user = $userHandler->getByUsername($key);
			}

			if (!empty($user) && is_numeric($user['uid'])) {
				$groupHandler->addUser($_REQUEST['categoryID'], $user['uid']);
			}
		}
	}

	SitePage::localRedir('/group/modify/' . $_REQUEST['categoryID']);
}

