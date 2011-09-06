<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');
require_once '../../library/eportfolio-blog.php';

$page = new SitePage();
$blogHandler = new ePortfolioBlog();

if (isset($_POST['delete'])) {
	$eportfolioHandler = new ePortfolio($_POST['uid']);

	if (!$eportfolioHandler->canEdit($_POST['uid'])) {
		error(107);
	}

	$blogHandler->delete($_POST['blogID']);

	$page->backToTheFuture(2);
}
else {
	if (!empty($_POST['heading'])) {
		$eportfolioHandler = new ePortfolio($_POST['uid']);

		if (!$eportfolioHandler->canEdit($_POST['uid'])) {
			error(107);
		}

		$blog = array(
			'heading' => $_POST['heading'],
			'body' => $_POST['body'],
		);

		/* Update existing, check ownership;
				 * otherwise create new blog entry
				 */
		if ($_POST['blogID']) {
			$blog['id'] = $_POST['blogID'];

			$blogHandler->update($blog);
		}
		else {
			$blogHandler->create($blog);
		}

		SitePage::localRedir('/eportfolio/' . $_POST['uid']);
	}
	else {
		error(1);
	}
}
?>
