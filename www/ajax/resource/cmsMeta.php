<?php
/**
 * Ajax callback for retrieving metadata about a folder / file (or files) in the resource browser view
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once('../../../include/Bootstrap.php');
use Schoolbox\Resource\FileMapper;
use Schoolbox\File\MimetypeMapper;

// kickstart a session
new SitePage();

// disallow SitePage context, this is an ajax script
if(isset($this)) {
	SitePage::localRedir('/ajax/resource/cmsMeta.php');
	exit;
}

// check 1: folder specified and sane
if(!(isset($_POST['folderId']) && is_numeric($_POST['folderId']))) {
	sendError('No folder ID specified');
}

$folderId = $_POST['folderId'];

// check 2: user logged in an has at minimum read access
$groupHandler = new Group();
$access = $groupHandler->getPermission($folderId);
if($access < READ_ACCESS) {
	sendError('User does not have access to this folder');
}

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

die(json_encode($response));




/**
 * Send an appropriate error and bug out
 *
 * @param $errString
 */
function sendError($errString) {
	header('HTTP/1.1 400 Bad Request');
	die(json_encode(array('error', $errString)));
}


/**
 * Send the metadata for the specified folder
 *
 * @param $folderId
 * @return array
 */
function getFolderMeta($folderId) {

	// special case for root directory
	if($folderId == 0) {
		return array(
			'name' => 'Top',
			'id' => 0,
			'actions' => array('file', 'folder')
		);
	}

	// handler setup
	$folderHandler = new MyFolder();

	$folder = $folderHandler->getFolderNode($folderId);
	$folder = $folder[0];

	if(!$folder) {
		sendError('No folder with specified ID exists');
	}

	$folder = array(
		'name' => $folder['name'],
		'id' => $folder['id'],
		'actions' => array('file', 'folder', 'delete', 'modify')
	);

	return $folder;

}


/**
 * Send the metadata for a single file
 *
 * @param $folderId
 * @param $fileId
 * @return array
 */
function getSingleFileMeta($folderId, $fileId) {

	$fileHandler = new MyFile();
	$file = $fileHandler->getByArray(array('id' => $fileId, 'deleted' => 0));

	if(!$file) {
		sendError('No file with the specified ID exists');
	}

	$file = $file[0];

	// write access provides actions
	$actions = array('modify', 'delete', 'open', 'download');

	// list of portfolio pages that can be published to
	$componentDefinition = Component_DefinitionMapper::getInstance()->findByColumn('class', 'Component_Eportfolio_FileListController');
	$availablePages = \Eportfolio\PageMapper::getInstance()->findAvailablePages($_SESSION['id'], $componentDefinition->current());
	$publishTo = array();

	if (is_array($availablePages) && count($availablePages)) {
		foreach ($availablePages as $currentPage) {
			if (is_array($currentPage['componentList']) && count($currentPage['componentList'])) {
				foreach ($currentPage['componentList'] as $currentComponent) {

					// Each link only needs to know this
					$pageData = array(
						'userId' => $_SESSION['id'],
						'pageId' => $currentPage['page']->getPageId(),
						'pageTitle' => $currentPage['page']->getPageTitle(),
						'componentInstanceId' => $currentComponent->id,
					);

					$publishTo[] = $pageData;
				}
			}
		}
	}

	$mtypeMap = new MimetypeMapper();

	$postDate = new Alaress_Date_Format($file['submitted'], 'y-m-d h:i:s');

	$file = array(
		'kind' => 'file',
		'id' => $file['id'],
		'name' => $file['description'],
		'mime' => $mtypeMap->getIconForFilename($file['realname']),
		'filesize' => Alaress_Byte_Format::bytesToString($file['file_size']),
		'posted_by' => 'you',
		'posted_date' => $postDate->getDateOnly(),
		'actions' => $actions,
		'publish' => $publishTo
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
	$groupHandler = new Group();
	$access = $groupHandler->getPermission($folderId);

	if($access <= READ_ACCESS) {
		return array(
			'actions' => array()
		);
	}
	else {
		return array(
			'actions' => array('delete')
		);
	}
}