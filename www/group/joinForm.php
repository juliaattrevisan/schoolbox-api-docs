<?php
use Schoolbox\Mapper;
use Schoolbox\Notif\Notif;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Schedule\Event;

require_once('../../include/Bootstrap.php');

$page = new SitePage();
$groupHandler = new Group(HOMEPAGE_SERVICE);

$response = 'error';

$uaMapper = UserAccessMapper::getInstance();

// handle both ajax and form submissions
$groupsSelected = [];
if (!empty($_POST['group'])) {
	$groupsSelected = $_POST['group'];
} elseif (is_numeric($_GET['join'])) {
	$groupsSelected[$_GET['join']] = true;
} elseif (is_numeric($_GET['leave'])) {
	$groupsSelected[$_GET['leave']] = false;
}

foreach ($groupsSelected as $groupId => $isJoining) {
	if ($isJoining) {
		$group = $groupHandler->getByID($groupId);

		$ua = new UserAccess();
		$ua
			->setService(UserAccess::SERVICE_HOMEPAGE)
			->setServiceIdentifier($groupId)
			->setUser($_SESSION['user'])
			->setAccessLevel(UserAccess::ACCESS_DENY);

		if ($group['type'] == GROUP_TYPE_FREE) {
			// allow read access immediately for a free group
			$ua->setAccessLevel(UserAccess::ACCESS_READ);
			$uaMapper->save($ua);
			$response = 'joined';
		}
		else if ($group['type'] == GROUP_TYPE_MODERATED) {
			// insert the user into the perms table, but deny them access (pending state)
			$uaMapper->save($ua);
			$response = 'pending';
		}

	} else {
		// delete the record that matches the given params
		$count = $uaMapper->deleteByColumnList(array(
			'service' => UserAccess::SERVICE_HOMEPAGE,
			'uid'     => $_SESSION['user']->getId(),
			'sid'     => $groupId
		));

		if($count) {
			$response = 'left';

			// we need to manually raise a DELETE notification
			// since the Mapper can't automagically do it for this Model
			$model = new UserAccess();
			$model->setUser($_SESSION['user'])
				->setAccessLevel(0)
				->setService(UserAccess::SERVICE_HOMEPAGE)
				->setServiceIdentifier($groupId);

			$event = new Event($_SESSION['user'], Mapper::METHOD_DELETE, $model, $uaMapper);
			$notif = new Notif(Notif::TOPIC_APPLICATION);
			$notif->notice(serialize($event));
		}
	}
}

// if there is an ajax flag, return a json response
if ($page->detectAjax()) {
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