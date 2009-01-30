<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';

$page = new SitePage;
$groupHandler = new Group(HOMEPAGE_SERVICE);


if (is_numeric($_GET['join'])) {
	$group = $groupHandler->getByID($_GET['join']);
	if ($group['type'] == GROUP_TYPE_FREE)
		$groupHandler->addUser($_GET['join'], $_SESSION['id']);
	elseif ($group['type'] == GROUP_TYPE_MODERATED)
		$groupHandler->addUser($_GET['join'], $_SESSION['id'], DENY_ACCESS);
}
elseif ($_GET['leave']) {
	$groupHandler->deleteUser($_GET['leave'], $_SESSION['id']);
}

localRedir('/group/join');
?>