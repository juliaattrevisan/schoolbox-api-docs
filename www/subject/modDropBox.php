<?php

require_once("../include/include.php");
require_once("../include/session.php");
require_once("../include/fileSecurity.php");
require_once('dropbox.php');

$dropbox = new dropbox;
if(is_numeric($_GET['delete'])) {
	$cid = $dropbox->deleteFile($_GET['delete']);
	localRedir("/homepage.php?category=$cid");
}
else if(is_numeric($_GET['id'])) {
	$dropbox->outputFile($_GET['id'], $_GET['save']);
}
else if(is_numeric($_POST['category']) && $_POST['description'] && $_FILES['file']['tmp_name']) {
	
	fileSecurityCheck($_FILES['file']['error'], $_FILES['file']['name'], $_FILES['file']['tmp_name'], $_FILES['file']['size'], $_FILES['file']['type']);
	
	$fileId = $dropbox->addFile($_FILES['file']['name'], $_FILES['file']['type'], $_FILES['file']['tmp_name'], $_POST['description'], $_POST['category']);
	localRedir("/homepage.php?category=".$_POST['category']);
}
else
	error(2);

?>
