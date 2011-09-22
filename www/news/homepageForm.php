<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage;
$newsHandler = new CategoryNews;
$groupHandler = new Group;

if($_POST['heading'] && is_numeric($_POST['code'])) {

	if (($permission = $groupHandler->getPermission($_POST['code'])) < WRITE_ACCESS)
		error(19);

    $publishedDate = new DateTime( str_replace('/', '-', $_POST['datetime_published']) );
    $expiryDate = new DateTime( str_replace('/', '-', $_POST['datetime_expires']) );

    $news = array(
        'heading' => $_POST['heading'],
        'body' => $_POST['body'],
        'code' => $_POST['code'],
        'datetime_published' => $publishedDate->format(Cfg::get('date_database')),
        'datetime_expires' => $expiryDate->format(Cfg::get('date_database')),
    );

	if(is_numeric($_POST['id'])) {
	
		$current = $newsHandler->getByID($_POST['id']);

		// check user is owner
		if ($permission == WRITE_ACCESS && $current['owner'] != $_SESSION['id'] && !NEWS_teacher_edit_other)
			error(23);
		
		/* Delete news */
		if($_POST['delete']) {
			$newsHandler->delete($_POST['id']);
		}
		else {
			/* Modify existing news */
			$news['id'] = $_POST['id'];

			$newsHandler->update($news); 
		}
	}
	else {
		
		/* Create news */
		$news['owner'] = $_SESSION['id'];

		$newsHandler->create($news);
	}

	$page->backToTheFuture(2);
}
else
	error(2);