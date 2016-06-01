<?php
require_once(dirname(__FILE__) . '/../../library/Bootstrap.php');

$page = new SitePage();

if (!$_SESSION['user']->isSuperuser()) {
	error(19);
}

$menuHandler = new SideMenu();

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
elseif ($_POST['txt']) {
	$menuHandler->create($menuItem);
	SitePage::localRedir('/adminv2/side-menu/');
}
/* Ajax drag and drop request
 */
elseif (isset($_GET['pos']) && isset($_GET['tid']) && isset($_GET['oid'])) {
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
        default:
            // move this node to that position within the parent.
            $menuHandler->moveItemToPosition($_GET['oid'], $_GET['tid'], intval($_GET['pos']));
            break;
	}
}
else {
	error(2);
}


