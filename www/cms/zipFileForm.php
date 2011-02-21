<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/file.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/allowed-file.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/zipFileUpload.php';

require_once('../include/pclzip.lib.php');

$page = new SitePage;
$fileHandler = new File;
$groupHandler = new Group;

/* Force session usage due to flash uploader
 * http://www.uploadify.com/forums/discussion/43/using-sessions-tricking-basic-authentication/p1
 */
if (isset($_POST[session_name()])) {
	session_id($_POST[session_name()]);
}

if (isset($_GET[session_name()])) {
	session_id($_GET[session_name()]);
}

/* Extract files
 */
$archive = new PclZip($_FILES['file']['tmp_name']);
echo "<pre>";
$zipFileUploadHandler = new ZipFileUpload();
$zipFileUploadHandler->extract($archive);
$zipFileUploadHandler->traverse($zipFileUploadHandler->getPath());
$zipFileUploadHandler->cleanup();

print_r($zipFileUploadHandler->lastDir);
?>
