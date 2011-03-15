<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/news.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$page = new SitePage;
$newsHandler = new News;
$groupHandler = new Group(NEWS_SERVICE);

if (($permission = $groupHandler->getPermission(0)) < WRITE_ACCESS)
	error(19);

if ($_POST['heading']) {
	$news = array('item_heading' 	=> $_POST['heading'],
                      'item_body'	=> $_POST['body'],
                      'groupList'	=> $_POST['group'],
                      'campusList'	=> $_POST['campus'],
                      'userList'        => $_POST['userList'],
                      'item_topic_id' 	=> $_POST['item_topic_id'],
                    );
	
	if ((int)$_POST['expires'] > 0)
		$news['item_datetime_expires'] = outputDate(DATE_database, strtotime("+" . $_POST['expires'] . " hours"));

	if (is_numeric($_POST['newsID'])) {
		
		// check user is news owner
		$current = $newsHandler->getByID($_POST['newsID']);
		if($current['item_owner'] != $_SESSION['id'] && $permission < FULL_ACCESS)
			error(23);	

		$news['item_id'] = $_POST['newsID'];
		$newsHandler->update($news);
	} else
		$newsHandler->create($news);

	localRedir("/news/");
} 
else if (is_numeric($_GET['delete'])) {
	// check user is news owner
	$news = $newsHandler->getByID($_GET['delete']);
	if($news['item_owner'] != $_SESSION['id'] && $permission < FULL_ACCESS)
		error(23);
	
	$newsHandler->delete($_GET['delete']);
	localRedir("/news/");
}
?>