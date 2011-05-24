<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once SITE_root . '/library/sideMenu.php';
require_once SITE_root . '/library/group.php';

$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
    error(19);
}

$menuHandler = new sideMenu();

$menuItem = array('id'      => $_POST['id'],
                  'txt'     => $_POST['txt'],
                  'url'     => $_POST['url'],
                  'target'  => $_POST['target'],
                  'campus'  => $_POST['campus'],
                  'group'   => $_POST['group']);
if($_POST['delete'] && $_POST['id']) {
    $menuHandler->delete($_POST['id']);
     localRedir('/adminv2/side-menu/');
}
if(is_numeric($_POST['id']) && $_POST['id'] && $_POST['txt']) {
    /* Modify */
    $menuHandler->update($menuItem);
    localRedir('/adminv2/side-menu/');
}
else if($_POST['txt']) {
    $menuHandler->create($menuItem);
    localRedir('/adminv2/side-menu/');
}
else if($_GET['pos'] && $_GET['tid'] && $_GET['oid']) {
    /* Ajax drag and drop request */
    switch($_GET['pos']) {
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
else
    error(2);


?>
