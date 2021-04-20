<?php
require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

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

