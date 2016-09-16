<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/Bootstrap.php';

$page = new SitePage();
$myLinksHandler = new MyLinks();

if (isset($_REQUEST['url']) && $_REQUEST['url'] && isset($_REQUEST['description']) && $_REQUEST['description']) {
	$urlFormatter = new Url($_REQUEST['url']);
    try{
        $urlFormatter->normalise();
    } catch (InvalidArgumentException $e) {
        error(\Schoolbox\Error\Mapper::ERROR_LINK_FORMAT);
    }

    $url = Scrub::text($urlFormatter->getUrl());

	/* Create/update
	 */
	$existing = $myLinksHandler->getByID($_POST['linkID']);

	if ($existing) {
		$existing['url'] = $url;
		$existing['description'] = $_REQUEST['description'];

		$myLinksHandler->update($existing);
	}
	else {
		$myLinksHandler->create(array(
			'owner' => $_SESSION['id'],
			'url' => $url,
			'description' => $_REQUEST['description']
		));
	}
}
else {
	if (is_numeric($_GET['delete'])) {
		if (!$link = $myLinksHandler->getByID($_GET['delete'])) {
			$page->error(2);
		}

		if ($link['owner'] != $_SESSION['id']) {
			$page->error(50);
		}

		$myLinksHandler->delete($_GET['delete']);
	}
}

if (isset($_REQUEST['ajax'])) {
	die(json_encode(['result' => 'success']));
} elseif($_GET['return'])
    SitePage::backToTheFuture(1);
else
    SitePage::localRedir('/cms/myLinks');
