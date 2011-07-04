<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/my-links.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/eportfolio.php');

$page = new SitePage;
$myLinksHandler = new MyLinks();

if (isset($_REQUEST['url']) && $_REQUEST['url'] && isset($_REQUEST['description']) && $_REQUEST['description']) {

    if(!isURL($_REQUEST['url']))
        error(139);

    if($myLinksHandler->linkExists($_SESSION['id'],$_REQUEST['url']))
        error(140);

	$url = scrubTEXT(trim($_REQUEST['url']));

	$myLinksHandler->create(array(
		'owner' => $_SESSION['id'],
		'url' => $url,
		'description' => $_REQUEST['description']
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
