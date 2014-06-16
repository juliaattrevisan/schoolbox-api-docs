<?php
require_once('../../include/Bootstrap.php');

$page = new SitePage();
$groupHandler = new Group(HOMEPAGE_SERVICE);

$response = 'error';

if (is_numeric($_GET['join'])) {
	$group = $groupHandler->getByID($_GET['join']);

	$ua = new \Schoolbox\Permission\Access\UserAccess();
	$ua
		->setService(\Schoolbox\Permission\Access\UserAccess::SERVICE_HOMEPAGE)
		->setServiceIdentifier($_GET['join'])
		->setUser($_SESSION['user'])
		->setAccessLevel(\Schoolbox\Permission\Access\UserAccess::ACCESS_DENY);

	if ($group['type'] == GROUP_TYPE_FREE) {
		// allow read access immediately for a free group
		$ua->setAccessLevel(\Schoolbox\Permission\Access\UserAccess::ACCESS_READ);
		\Schoolbox\Permission\Access\UserAccessMapper::getInstance()->save($ua);
		$response = 'joined';
	}
	else if ($group['type'] == GROUP_TYPE_MODERATED) {
		// insert the user into the perms table, but deny them access (pending state)
		\Schoolbox\Permission\Access\UserAccessMapper::getInstance()->save($ua);
		$response = 'pending';
	}

}
else if ($_GET['leave']) {
	// delete the record that matches the given params
	$count = \Schoolbox\Permission\Access\UserAccessMapper::getInstance()->deleteByColumnList(array(
		'service' => \Schoolbox\Permission\Access\UserAccess::SERVICE_HOMEPAGE,
		'uid'     => $_SESSION['user']->getId(),
		'sid'     => $_GET['leave']
	));

	if($count) {
		$response = 'left';
	}
}

// if there is an ajax flag, return a json response
if(is_numeric($_GET['ajax'])) {
	header($_SERVER['SERVER_PROTOCOL'] . ' 202 Accepted');
	header('Content-Type: application/json');
	die(json_encode(array('status' => $response)));
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