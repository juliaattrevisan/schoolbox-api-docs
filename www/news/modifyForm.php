<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();
$newsHandler = new News();
$groupHandler = new Group(NEWS_SERVICE);

if (($permission = $groupHandler->getPermission(0)) < WRITE_ACCESS) {
	error(19);
}

if ($_POST['heading']) {
	$publishedDate = new Alaress_Date_Format($_POST['item_datetime_published'], 'd/m/y');
	$expiryDate = new Alaress_Date_Format($_POST['item_datetime_published'], 'd/m/y');

	$news = array(
		'item_heading' => $_POST['heading'],
		'item_body' => $_POST['body'],
		'groupList' => $_POST['group'],
		'campusList' => $_POST['campus'],
		'userList' => $_POST['userList'],
		'item_topic_id' => $_POST['item_topic_id'],
		'item_datetime_published' => $publishedDate->getDatabase(),
		'item_datetime_expires' => $expiryDate->getDatabase()
	);

	/* Check user is news owner
	 */
	if (is_numeric($_POST['newsID'])) {
		$current = $newsHandler->getByID($_POST['newsID']);

		if ($current['item_owner'] != $_SESSION['id'] && $permission < FULL_ACCESS) {
			error(23);
		}

		$news['item_id'] = $_POST['newsID'];
		$newsHandler->update($news);
	}
	else {
		$newsHandler->create($news);
	}

	SitePage::localRedir("/news/");
}
/* Check user is news owner
 */
else if (is_numeric($_GET['delete'])) {
	$news = $newsHandler->getByID($_GET['delete']);

	if ($news['item_owner'] != $_SESSION['id'] && $permission < FULL_ACCESS) {
		error(23);
	}

	$newsHandler->delete($_GET['delete']);

	SitePage::localRedir("/news/");
}
