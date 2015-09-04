<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage;
$groupHandler = new Group;

if(!($_POST['heading'] && is_numeric($_POST['code']))) {
	error(2);
}

if(($permission = $groupHandler->getPermission($_POST['code'])) < WRITE_ACCESS) {
	error(19);
}

if(is_numeric($_POST['id'])) {
	try {
		$news = \Schoolbox\Resource\Category\NewsMapper::getInstance()->findById($_POST['id']);
	}
	catch(\Schoolbox\Mapper\Exception $e) {
		error(23);
		exit;
	}
}
else {
	$news = new Schoolbox\Resource\Category\News();
	$news->setOwner($_SESSION['user']);
	$news->setPostDate(new Alaress_Date_Format(time(), 'U'));
}

// check user is owner, or other users can edit other's news
if ($permission == WRITE_ACCESS
	&& $news->getOwner()->getId() != $_SESSION['id']
	&& !Cfg::get('news_edit_other')
) {
	error(23);
}

// setup various data
$cat = new \Schoolbox\Resource\Category();
$cat->setId($_POST['code']);
$publishedDate = Alaress_Date_Format::newLocalShort($_POST['datetime_published']);
$expiryDate = Alaress_Date_Format::newLocalShort($_POST['datetime_expires']);

$news
	->setHeading($_POST['heading'])
	->setBody($_POST['body'])
	->setCategory($cat)
	->setPublishDate($publishedDate)
	->setExpiryDate($expiryDate);

/* Delete news */
if($_POST['type'] == 'delete') {
	\Schoolbox\Resource\Category\NewsMapper::getInstance()->delete($news);
}
else {
	\Schoolbox\Resource\Category\NewsMapper::getInstance()->save($news);
}

$page->backToTheFuture(2);
