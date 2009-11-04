<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/allowed-file.php';

require_once SITE_root . '/library/file.php';
require_once SITE_root . '/library/link.php';


$page = new SitePage;
$output = array();

$allowFilter = array('image', 'video');

if (is_numeric($_POST['categoryID'])) {
	$fileHandler = new File;
	$linkHandler = new Link;
	$allowedFiles = new AllowedFile;

	
	$fileList = $fileHandler->getAllInCategory($_POST['categoryID'],  in_array($_POST['filter'], $allowFilter) ? array('filter' => $_POST['filter']) : false);
	
	if (!in_array($_POST['filter'], $allowFilter)) {
		
		$linkList = $linkHandler->getAllInCategory($_POST['categoryID']);
		$fileList = $fileHandler->mergeLinks( $fileList, $linkList);
	}
	if (!is_array($fileList)) {
		$output = array();
	} else {
		foreach ($fileList as $file) {
			$file['user'] = formatName($file);
			$file['relative'] = getRelativeTime($file['modified'], 0, '');
			$file['mimeClass'] = !empty($file['iconFile']) ? $allowedFiles->getClass($file["iconFile"]) : "textfile";
			$file['description'] = cutString($file['description'], 32);
			$output[] = $file;
		}
	}
	
	$_SESSION['resourcesID'] = $_POST['categoryID'];
}

header("Content-type: application/json");
print json_encode($output);

?>