<?php
require_once('../../include/Bootstrap.php');

$page = new SitePage();
$groupHandler = new Group(HOMEPAGE_SERVICE);

if (is_numeric($_GET['join'])) {
	$group = $groupHandler->getByID($_GET['join']);
	$response = 'error';

	$ua = new \Schoolbox\UserAccess();
	$ua
		->setService(\Schoolbox\UserAccess::SERVICE_HOMEPAGE)
		->setItemId($_GET['join'])
		->setUser($_SESSION['user'])
		->setAccessLevel(\Schoolbox\UserAccess::ACCESS_DENY);

	if ($group['type'] == GROUP_TYPE_FREE) {
		// allow read access immediately for a free group
		$ua->setAccessLevel(\Schoolbox\UserAccess::ACCESS_READ);
		\Schoolbox\UserAccessMapper::getInstance()->save($ua);
		$response = 'joined';
	}
	else if ($group['type'] == GROUP_TYPE_MODERATED) {
		// insert the user into the perms table, but deny them access (pending state)
		\Schoolbox\UserAccessMapper::getInstance()->save($ua);
		$response = 'pending';
	}



	// if there is an ajax flag, return a json response
	if(is_numeric($_GET['ajax'])) {
		header($_SERVER['SERVER_PROTOCOL'] . ' 202 Accepted');
		header('Content-Type: application/json');
		die(json_encode(array('status' => $response)));
	}

}
else if ($_GET['leave']) {
	try {
		// retrieve it so we can delete it!
		$ua = \Schoolbox\UserAccessMapper::getInstance()->findOneByColumnList(array(
			'service' => \Schoolbox\UserAccess::SERVICE_HOMEPAGE,
			'uid'     => $_SESSION['user']->getId(),
			'sid'     => $_GET['leave']
		));

		\Schoolbox\UserAccessMapper::getInstance()->delete($ua);
	}
	catch(\Schoolbox\Mapper\Exception $e) {
		// noop
	}
}

/* If the Return parameter is set, go back to the referring page.
 * Otherwise redirect to the generic group/join page
 */
if ($_GET['return']) {
	$page->backToTheFuture(1);
}
else {
	SitePage::localRedir('/group/join');
}

exit();