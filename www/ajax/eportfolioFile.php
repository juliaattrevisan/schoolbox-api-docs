<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page = new SitePage();
$output = array();
$allowFilter = array('image', 'video');

if (is_numeric($_POST['categoryID'])) {
	$fileHandler = new MyFile();
	$linkHandler = new Link();
	$allowedFiles = new AllowedFile();

	$fileList = $fileHandler->getAllInFolder($_POST['categoryID'], in_array($_POST['filter'], $allowFilter) ? array('filter' => $_POST['filter']) : false);

	if (!in_array($_POST['filter'], $allowFilter)) {
		$linkList = $linkHandler->getAllInFolder($_POST['categoryID']);
		$fileList = $fileHandler->mergeLinks( $fileList, $linkList);
	}

	if (!is_array($fileList)) {
		$output = array();
	}
	else {
		$userHandler = new User();

		foreach ($fileList as $file) {
            $fileTime = new Alaress_Date_Relative($file['modified']);
            $fileTime->setPrefix('');

			$file['user'] = $userHandler->formatName($file);
			$file['relative'] = $fileTime->getRelativeTime();
			$file['mimeClass'] = !empty($file['iconFile']) ? $allowedFiles->getClass($file["iconFile"]) : "textfile";
			$file['description'] = TextFormatter::cutString($file['description'], 32);
			$output[] = $file;
		}
	}

	$_SESSION['resourcesID'] = $_POST['categoryID'];
}

header("Content-type: application/json");
print json_encode($output);

