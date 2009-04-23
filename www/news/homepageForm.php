<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/categoryNews.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$page = new SitePage;
$newsHandler = new CategoryNews;
$groupHandler = new Group;

if($_POST['heading'] && is_numeric($_POST['code'])) {

	if (($permission = $groupHandler->getPermission($_POST['code'])) < WRITE_ACCESS)
		error(19);

	$news = array(	'heading'	=> $_POST['heading'],
					'body'		=> $_POST['body'],
					'code'		=> $_POST['code'],
					
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
			$_POST['expires'] > 0 ? $news['datetime_expires'] = date(DATE_database, (mktime()+$_POST['expires']*3600)) : '';
			
			$newsHandler->update($news); 
		}
	}
	else {
		
		/* Create news */
		$news['owner'] = $_SESSION['id'];
		$news['datetime_expires'] = date(DATE_database, (mktime() + $_POST['expires']*3600));
		$newsHandler->create($news);
	}

	$page->backToTheFuture(2);
}
else
	error(2);