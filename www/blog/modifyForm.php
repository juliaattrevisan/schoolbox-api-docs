<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page = new SitePage();
$groupHandle = new Group();

if(($permission = $groupHandle->getPermission($_POST['cid'])) < WRITE_ACCESS) {
	error(19);
}

if(is_numeric($_POST['blogID'])) {
	$blog = \Schoolbox\Blog\BlogMapper::getInstance()->findById($_POST['blogID']);
}
else {
	$cat = new \Schoolbox\Resource\Folder\Model();
	$cat->setId($_POST['cid']);

	$blog = new \Schoolbox\Blog\Blog();
	$blog
		->setOwner($_SESSION['user'])
		->setCreated(new Alaress_Date_Format(time(), 'U'))
		->setCategory($cat);
}

if(isset($_POST['delete'])) {
	try {
		\Schoolbox\Blog\BlogMapper::getInstance()->delete($blog);
		$page->backToTheFuture(2);
	}
	catch(\Schoolbox\Mapper\Exception $e) {
		// no blog by that ID exists
		error(41);
	}
}
elseif(empty($_POST['heading'])) {
	error(2);
}

$blog
	->setHeading($_POST['heading'])
	->setBody($_POST['body']);

\Schoolbox\Blog\BlogMapper::getInstance()->save($blog);

SitePage::localRedir('/homepage/'.$_POST['cid']);
