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
	SitePage::localRedir('/ajax/resource/meta.php');
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

	// handler setup
	$catHandler = new Category();
	$groupHandler = new Group();
	$homeTemplateHandler = new HomepageTemplate();
	$cat = $catHandler->getByID($folderId);

	if(!$cat) {
		sendError('No folder with specified ID exists');
	}

	// determine the name of the template, if the folder has a homepage
	if($cat['template'] == 0) {
		$template = '';
	}
	else {
		$homeHandler = new Homepage($cat['id']);
		$template = $homeTemplateHandler->getByID($homeHandler->getTemplate());
	}

	// determine the group type (open, restricted, moderated)
	switch($cat['type']) {
		case GROUP_TYPE_RESTRICTED:
			$type = 'Restricted';
			break;
		case GROUP_TYPE_MODERATED:
			$type = 'Moderated';
			break;
		case GROUP_TYPE_FREE:
			$type = 'Open';
			break;
		default:
			$type = '';
			break;
	}

	// joinable: is the logged in user not already a member?
	$accessType = $groupHandler->involvedInGroup($_SESSION['id'], $folderId);

	// dear loose typing: bite me.
	if($accessType === '0') {
		$accessType = 0;
	}

	if($accessType === DENY_ACCESS && !is_null($type)) {
		$joinable = 2; // pending approval
	}
	elseif($groupHandler->canSubscribe($folderId)) {
		$joinable = 1; // can join
	}
	else {
		$joinable = 0; // either don't have access, or are already a member
	}

	// permissions: must have write access to see it
	$userAccess = $groupHandler->getPermission($folderId);
	if($userAccess >= WRITE_ACCESS) {
		$permissions = array();
		$permsList = $catHandler->getGroupPerms($folderId);
		if(is_array($permsList)) {
			foreach($permsList as $group) {
				$groupAccess = (int)$group['type'];
				if($groupAccess > 0) {
					$permissions[] = array(
						'group' => $group['name'],
						'access' => $groupAccess
					);
				}
			}
		}
	}
	else {
		$permissions = array();
	}


	// actions: if write, some; if full, all.
	$actions = array();
	switch($userAccess) {
		case FULL_ACCESS:
			$actions = array_merge($actions, array('zip'));
			// no break
		case WRITE_ACCESS:
			$actions = array_merge($actions, array('file', 'folder', 'delete', 'modify', 'download'));
			// no break
		case READ_ACCESS:
		default:
			// no actions available
			break;
	}

	if($template['name']) {
		$actions[] = 'homepage';
	}


	$folder = array(
		'name' => $cat['name'],
		'id' => $cat['id'],
		'template' => $template['name'],
		'type' => $type,
		'joinable' => $joinable,
		'permissions' => $permissions,
		'actions' => $actions,
		'debug' => $debug
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

	$groupHandler = new Group();
	$userHandler = new User();

	$fmap = new FileMapper();
	$file = $fmap->findByColumnList(array('id' => $fileId, 'deleted' => 0))->current();

	if(!$file) {
		sendError('No file with the specified ID exists');
	}

	$userAccess = $groupHandler->getPermission($folderId);

	// write access provides actions
	$actions = array();
	switch($userAccess) {
		case FULL_ACCESS:
		case WRITE_ACCESS:
			$actions = array_merge($actions, array('modify', 'delete'));
			// no break
		case READ_ACCESS:
			$actions = array_merge($actions, array('open', 'download', 'properties'));
			// no break
		default:
			// no actions available
			break;
	}

	$mtypeMap = new MimetypeMapper();

	$file = array(
		'kind' => 'file',
		'id' => $file->getId(),
		'name' => $file->getDescription(),
		'mime' => $mtypeMap->getIconForFilename($file->getFilename()),
		'filesize' => Alaress_Byte_Format::bytesToString($file->getFilesize()),
		'hidden' => $file->getHidden() ? 1 : 0,
		'description' => $file->getComment(),
		'posted_by' => $file->getOwner()->formatName(),
		'posted_date' => $file->getSubmitted()->getDateOnly(),
		'actions' => $actions
	);

	return $file;
}

/**
 * @param $folderId
 * @param $linkId
 * @return array
 */
function getSingleLinkMeta($folderId, $linkId) {
	$groupHandler = new Group();
	$linkHandler = new Link();
	$userHandler = new User();

	$link = $linkHandler->getByID($linkId);

	if(!$link || $link['deleted'] == 1) {
		sendError('No link with the specified ID exists');
	}

	$userAccess = $groupHandler->getPermission($folderId);
	$adf = new Alaress_Date_Format($link['submitted'], 'U');

	// actions based on access
	$actions = array();
	switch($userAccess) {
		case FULL_ACCESS:
		case WRITE_ACCESS:
			$actions = array_merge($actions, array('modify', 'delete'));
		// no break
		case READ_ACCESS:
			$actions = array_merge($actions, array('open', 'properties'));
		// no break
		default:
			// no actions available
			break;
	}

	$file = array(
		'kind' => 'link',
		'id' => $link['id'],
		'name' => $link['description'],
		'hidden' => $link['hidden'],
		'description' => $link['comment'],
		'posted_by' => $userHandler->formatName($userHandler->getByID($link['owner'])),
		'posted_date' => $adf->getDateOnly(),
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