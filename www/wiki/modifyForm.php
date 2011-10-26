<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();
$wikiHandler = new Wiki();
$groupHandler = new Group(HOMEPAGE_SERVICE);

if (isset($_POST['heading']) && is_numeric($_POST['categoryID'])) {
	$data = array(
		'heading' => $_POST['heading'],
		'body' => $_POST['body'],
	);

	if (is_numeric($_POST['wikiID']) && strlen($_POST['slug'])) {

		/* Wiki exists
		 */
		$wiki = $wikiHandler->getByCategoryAndSlug($_POST['categoryID'], $_POST['slug']);

		if (!isset($wiki['wikiID'])) {
			error(2);
		}

		if (($permission = $groupHandler->getPermission($wiki['categoryID'])) < READ_ACCESS) {
			error(19);
		}

		/* Check locked
		 */
		if ($wiki['locked'] == 'true' && $permission < WRITE_ACCESS) {
			error(19);
		}

		$data['wikiID'] = $wiki['wikiID'];
		$data['categoryID'] = $wiki['categoryID'];

		$data = $wikiHandler->createStubLinks($data);

		/* Check if there has been a revision added for this wiki since you
		 * last received a lastRevision id.
		 */
		$latestRevision = $wikiHandler->getLatestRevision($wiki['wikiID']);

		/* Save your version regardless
		 */
		$wikiHandler->update($data);

		/* If there was a version between the one you were editing and the one
		 * you just submitted, compare them
		 */
		if ($_POST['revision'] < $latestRevision['revisionID']) {
			SitePage::localRedir("/wiki/merge/$wiki[wikiID]");
		}
		else {
			$redir = '/wiki/' . $wiki['categoryID'] . '/' . $_POST['slug'];
		}
	}
	else if ($_POST['categoryID']) {

		/* Check permission
		 */
		if (($permission = $groupHandler->getPermission($_POST['categoryID'])) < READ_ACCESS) {
			error(19);
		}

		$data['categoryID'] = $_POST['categoryID'];

		/* If a slug was already provided, don't modify it
		 * Otherwise determine a slug
		 */
		if (strlen($_POST['slug'])) {
			$data['slug'] = $_POST['slug'];
		}
		else {
			$url = new Url($_POST['heading']);

			$data['slug'] = $url->getSlug();
		}

		$data = $wikiHandler->createStubLinks($data);

		$wikiHandler->create($data);

		$redir = '/wiki/' . $data['categoryID'] . '/' . $data['slug'];
	}

	SitePage::localRedir($redir);
}
else {
	if (is_numeric($_GET['revert']) && is_numeric($_GET['wikiID'])) {
		$wiki = $wikiHandler->getByID($_GET['wikiID']);

		/* Check permission
		 */
		if (($permission = $groupHandler->getPermission($wiki['categoryID'])) < WRITE_ACCESS) {
			error(19);
		}

		$wikiHandler->revert($wiki['wikiID'], $_GET['revert']);

		SitePage::localRedir('/wiki/' . $wiki['categoryID'] . '/' . $wiki['slug']);
	}
	else {
		if (is_numeric($_GET['delete'])) {
			$wiki = $wikiHandler->getByID($_GET['delete']);

			/* Check permission
			 */
			if (($permission = $groupHandler->getPermission($wiki['categoryID'])) < WRITE_ACCESS) {
				error(19);
			}

			$wikiHandler->delete($wiki['wikiID']);

			SitePage::localRedir('/homepage/' . $wiki['categoryID']);
		}
		else {
			if (is_numeric($_GET['toggleLock'])) {

				/* Wiki exists
				 */
				$wiki = $wikiHandler->getByID($_GET['toggleLock']);

				if (!isset($wiki['wikiID'])) {
					error(2);
				}

				/* Check permissions
				 */
				if ($groupHandler->getPermission($wiki['categoryID']) < WRITE_ACCESS) {
					error(19);
				}

				$data = array(
					'wikiID' => $wiki['wikiID'],
					'locked' => $wiki['locked'] == 'true' ? 'false' : 'true',
				);

				$wikiHandler->update($data);

				$page->backToTheFuture(1);
			}
			else {
				error(2);
			}
		}
	}
}

