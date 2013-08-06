<?php

require_once '../../include/SitePage.php';

$page = new SitePage;

$dropbox = new Dropbox;
if(is_numeric($_GET['delete'])) {
	$cid = $dropbox->deleteFile($_GET['delete']);
	SitePage::localRedir("/homepage/$cid");
}
else if(is_numeric($_GET['id'])) {
	$dropbox->outputFile($_GET['id'], $_GET['save']);
}
else if(is_numeric($_POST['category']) && $_POST['description'] && $_FILES['file']['tmp_name']) {

	$allowedFileHandler = new AllowedFile;
	$allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES['file']));

	if ($fileId = $dropbox->addFile($_FILES['file']['name'], $_FILES['file']['type'], $_FILES['file']['tmp_name'], $_POST['description'], $_POST['category']))
		$_SESSION['dropSuccess'] = 1;

	SitePage::localRedir("/homepage/".$_POST['category']);
}
else
	error(2);

?>
