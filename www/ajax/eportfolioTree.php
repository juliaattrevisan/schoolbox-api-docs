<?php
require_once dirname(__FILE__) . '/../../library/Bootstrap.php';

$page = new SitePage();
$output = array();

if (is_numeric($_POST['relationID'])) {
	$folderHandler = new MyFolder();
	$allFolders = $folderHandler->getFolderList($_POST['relationID']);

	if (!is_array($allFolders)) {
		$output = array();
	}
	else {
		foreach ($allFolders as $currentFolder) {
			$output[] = $currentFolder;
		}
	}

	if (empty($output)) {
		$output = array('error' => 'This folder does not contain any visible subfolders.');
	}

	$_SESSION['resourcesID'] = $_POST['relationID'];
}
else if (is_numeric($_POST['resourcesID'])) {
	$_SESSION['resourcesID'] = $_POST['resourcesID'];
}

header("Content-type: application/json");
print json_encode($output);

