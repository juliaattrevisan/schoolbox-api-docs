<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();
$eportfolioHandler = new ePortfolio($_SESSION['id']);
$linkHandler = new Eportfolio_Link();
$groupHandler = new Group();

if ($_POST['url'] && $_POST['description']) {
	$link = array(
		'url' => $_POST['url'],
		'description' => $_POST['description'],
		'comment' => $_POST['comment'],
		'hidden' => $_POST['hidden'],
	);

	/* Modify existing link
		 */
	if (is_numeric($_POST['linkID'])) {
		$currentLink = $linkHandler->getById($_POST['linkID']);

		if (!$eportfolioHandler->canEdit($currentLink['owner'])) {
			error(6);
		}

		$link['id'] = $_POST['linkID'];
		$linkHandler->update($link);

		$page->backToTheFuture(2);
	}
	else {
		error(2);
	}
}
else {
	if ($_GET['delete']) {
		if (!$link = $linkHandler->getByID($_GET['delete'])) {
			error(2);
		}

		if (!$eportfolioHandler->canEdit($link['owner'])) {
			error(106);
		}

		$linkHandler->delete($_GET['delete']);

		if ($_GET['redirect'] == '') {
			$page->backToTheFuture(1);
		}
		else {
			SitePage::localRedir($_GET['redirect']);
		}
	}
	else {
		error(2);
	}
}
