<?php
require_once('../../include/Bootstrap.php');

$page = new SitePage();
$groupHandler = new Group(HOMEPAGE_SERVICE);

if (is_numeric($_GET['join'])) {
	$group = $groupHandler->getByID($_GET['join']);

	if ($group['type'] == GROUP_TYPE_FREE) {
		$groupHandler->addUser($_GET['join'], $_SESSION['id']);
	}
	else if ($group['type'] == GROUP_TYPE_MODERATED) {
		$groupHandler->addUser($_GET['join'], $_SESSION['id'], DENY_ACCESS);
	}
}
else if ($_GET['leave']) {
	$groupHandler->deleteUser($_GET['leave'], $_SESSION['id']);
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