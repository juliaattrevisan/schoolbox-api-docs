<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();
$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
	error(19);
}

$menuHandler = new sideMenu();

$menuItem = array(
	'id'     => $_POST['id'],
	'txt'    => $_POST['txt'],
	'url'    => $_POST['url'],
	'target' => $_POST['target'],
    'parent' => $_POST['parent'],
	'campus' => $_POST['campus'],
	'group'  => $_POST['group']
);

if ($_POST['delete'] && $_POST['id']) {
	$menuHandler->delete($_POST['id']);
	SitePage::localRedir('/adminv2/side-menu/');
}

/* Modify
 */
if (is_numeric($_POST['id']) && $_POST['id'] && $_POST['txt']) {
	$menuHandler->update($menuItem);
	SitePage::localRedir('/adminv2/side-menu/');
}
else if ($_POST['txt']) {
	$menuHandler->create($menuItem);
	SitePage::localRedir('/adminv2/side-menu/');
}
/* Ajax drag and drop request
 */
else if ($_GET['pos'] && isset($_GET['tid']) && $_GET['oid']) {
	switch ($_GET['pos']) {
		case 'before' :
			$menuHandler->moveItemBefore($_GET['oid'], $_GET['tid']);
			break;
		case 'after' :
			$menuHandler->moveItemAfter($_GET['oid'], $_GET['tid']);
			break;
		case 'last' :
			$menuHandler->moveItemLast($_GET['oid'], $_GET['tid']);
			break;
	}
}
else {
	error(2);
}


