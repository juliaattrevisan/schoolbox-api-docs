<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/my-links.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/eportfolio.php');

$page = new SitePage;
$myLinksHandler = new MyLinks();

if (isset($_POST['url']) && $_POST['url'] && isset($_POST['description']) && $_POST['description']) {
	$url = scrubTEXT(trim($_POST['url']));

	/* Local url:
	 * http://ilearn
	 * http://ilearn/
	 * http://ilearn/test/blah
	 */
	if (preg_match('#^(http://)([^\.]+)/(.*)?#i', $url, $matches) || preg_match('#^(http://)([^\.]+)$#i', $url, $matches)) {

		/* Remove unused matches
		 */
		unset($matches[0]);
		unset($matches[1]);

		/* Remove empty elements
		 */
		foreach(array_keys($matches, '') as $element) {
			unset($matches[$element]);
		}

		$url = '//' . implode('/', $matches);
	}

	/* Local url:
	 * //ilearn
	 * //ilearn/test/blah
	 */
	else if (preg_match('#^//(.*)?#i', $url, $matches)) {
		$url = $matches[0];
	}

	/* Local url:
	 * ilearn
	 * ilearn/test/blah
	 */
	else if (preg_match('#^([^\.]+)$#', $url, $matches)) {
		$url = '//' . $matches[0];
	}

	/* External url:
	 * google.com
	 * www.google.com
	 * http://www.google.com
	 * https://www.google.com
	 */
	else {
		/* Normal modification to url
		 * Just return url if it includes protocol
		 * Otherwise add http protocol if it was not found
		 */
		if (!preg_match('#http(s?)://(.*)#i', $url, $matches)) {
			$url = 'http://' . $url;
		}
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
