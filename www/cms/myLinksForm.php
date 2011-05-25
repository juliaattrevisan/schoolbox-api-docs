<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/my-links.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/eportfolio.php');

$page = new SitePage;
$myLinksHandler = new MyLinks();

if (isset($_POST['url']) && $_POST['url'] && isset($_POST['description']) && $_POST['description']) {
	$url = scrubTEXT(trim($_POST['url']));

	if (isLocalURL($url)) {
		$url = relativeURL($url);
	}

	$myLinksHandler->create(array(
		'owner' => $_SESSION['id'],
		'url' => $url,
		'description' => scrubTEXT($_POST['description'])
	));
}
else if (is_numeric($_GET['delete'])) {
	if (!$link = $myLinksHandler->getByID($_GET['delete'])) {
		$page->error(2);
	}

	if ($link['owner'] != $_SESSION['id']) {
		$page->error(50);
	}

	$myLinksHandler->delete($_GET['delete']);
}
else if (is_numeric($_GET['share'])) {
	$ePortfolioHandler = new ePortfolio($_SESSION['id']);

	if ($ePortfolioHandler->isEnabled()) {
		if (!$myLinksHandler->shareLink($_GET['share'])) {
			error(52);
		}
	}
	else {
		error(100);
	}
}

localRedir('/cms/myLinks');
?>
