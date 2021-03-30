<?php
/**
 * Ajax callback for retrieving metadata about a folder / file (or files) in the resource browser view
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\File\MimetypeMapper;

// disallow SitePage context, this is an ajax script
if (isset($this)) {
    SitePage::localRedir('/ajax/resource/networkMeta.php');
    exit;
}

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

// kickstart a session
new SitePage();

// check 1: folder specified and sane
if(!(isset($_POST['folderId']) && is_numeric($_POST['folderId']))) {
	sendError('No folder ID specified');
}

$folderId = $_POST['folderId'];

// check 2: user logged in an has at minimum read access
$groupHandler = new Group();

// check 3: if no files specified, return folder
if(!isset($_POST['files'])) {
	$response = getFolderMeta($folderId);
	die(json_encode($response));
}

// check 4: files correctly specified
if(!is_array($_POST['files'])) {
	sendError('Invalid file ID(s) specified');
}


// check 5: single or multi?
if(count($_POST['files']['file']) + count($_POST['files']['link']) == 1) {
	if(isset($_POST['files']['file'])) {
		$response = getSingleFileMeta($folderId, $_POST['files']['file'][0]);
	}
	else {
		$response = getSingleLinkMeta($folderId, $_POST['files']['link'][0]);
	}
}
else {
	$response = getMultiFileMeta($folderId);
}

header('Content-Type: application/json');
die(json_encode($response));




/**
 * Send an appropriate error and bug out
 *
 * @param $errString
 */
function sendError($errString) {
	http_response_code(400);
	header('Content-Type: application/json');
	die(json_encode(array('error', $errString)));
}


/**
 * Send the metadata for the specified folder
 *
 * @param $folderId
 * @return array
 */
function getFolderMeta($folderId) {

	// if it's the root node, return add-folder functions if admin
	if($folderId == 0) {
		$folder = array(
			'name' => 'Network Drive',
			'id' => 0,
			'template' => '',
			'type' => '',
			'joinable' => 0,
			'permissions' => array(),
			'actions' => array(),
		);
		return $folder;
	}

	// handler setup
	$davFolderHandler = PortfolioWebDAV::factory();

	// take the fist entry from the list of directories
	$folderList = $davFolderHandler->getFolderNode($folderId);


	foreach($folderList as $f) {
		if($f['id'] == $folderId) {
			$folder = $f;
			break;
		}
	}
	unset($f);

	if(!isset($folder)) {
		sendError('No folder with the specified ID exists');
	}

	$data = array(
		'name' => $folder['name'],
		'id' => $folderId,
		'template' => '',
		'type' => '',
		'joinable' => 0,
		'permissions' => array(),
		'actions' => array('file', 'folder', 'modify'),
	);

	return $data;

}


/**
 * Send the metadata for a single file
 *
 * @param $folderId
 * @param $fileId
 * @return array
 */
function getSingleFileMeta($folderId, $fileId) {

	$davFileHandler = PortfolioWebDAV::factory();
	$file = $davFileHandler->getByID($fileId);

	if(!$file) {
		sendError('No file with the specified ID exists');
	}

	// write access provides actions
	$actions = array('modify', 'delete', 'download');

	$mtypeMap = new MimetypeMapper();

	try {
		$mimetype = $mtypeMap->getByFilename($file['name']);
	}
	catch (\Exception $e) {
		$mimetype = $mtypeMap->getUnknownMime();
	}


	$modified = new Alaress_Date_Format(strtotime($file['submitted']), 'U');
    $created = new Alaress_Date_Format(strtotime($file['ctime']), 'U');

	$file = array(
		'kind' => 'file',
		'id' => $fileId,
		'name' => urldecode($file['description']),
		'mime' => $mimetype->getIconFile(),
		'filesize' => Alaress_Byte_Format::bytesToString($file['file_size']),
		'hidden' => 0,
		'description' => urldecode($file['description']),
		'posted_by' => 'you',
		'posted_date' => $created->getRelativeTime(),
		'modified' => $modified->getRelativeTime(),
		'actions' => $actions
	);

	return $file;
}


/**
 * Send action-permissions available for the files specified.
 * Pretty much a reduced version of sendFolderMeta()
 *
 * @param $folderId
 * @return array
 */
function getMultiFileMeta($folderId) {
	// if has write permission, grant delete access
		return array(
			'actions' => array('delete')
		);
}
