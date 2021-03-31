<?php

/**
 * Ajax callback for retrieving metadata about a folder / file (or files) in the resource browser view
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

// disallow SitePage context, this is an ajax script
if (isset($this)) {
    SitePage::localRedir('/ajax/resource/cmsMeta.php');
    exit;
}

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

// kickstart a session
new SitePage();

// check 1: folder specified and sane
if (!(isset($_POST['folderId']) && is_numeric($_POST['folderId']))) {
    sendError('No folder ID specified');
}

$folderId = $_POST['folderId'];

// check 2: user logged in and specified folder exists
$myFolderHandler = new MyFolder();

$myFolder = $myFolderHandler->getByID($folderId);

if (!is_array($myFolder) && $folderId > 0) {
    sendError('User does not have access to this folder');
}

// check 3: if no files specified, return folder
if (!isset($_POST['files'])) {
    $response = getFolderMeta($folderId);
    die(json_encode($response));
}

// check 4: files correctly specified
if (!is_array($_POST['files'])) {
    sendError('Invalid file ID(s) specified');
}


// check 5: single or multi?
if (count($_POST['files']['file']) + count($_POST['files']['link']) == 1) {
    if (isset($_POST['files']['file'])) {
        $response = getSingleFileMeta($folderId, $_POST['files']['file'][0]);
    } else {
        $response = getSingleLinkMeta($folderId, $_POST['files']['link'][0]);
    }
} else {
    $response = getMultiFileMeta($folderId);
}

header('Content-Type: application/json');
die(json_encode($response));




/**
 * Send an appropriate error and bug out
 *
 * @param $errString
 */
function sendError($errString)
{
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
function getFolderMeta($folderId)
{

    // special case for root directory
    if ($folderId == 0) {
        return array(
            'name' => 'Top',
            'id' => 0,
            'actions' => array('file', 'folder')
        );
    }

    // handler setup
    $folderHandler = new MyFolder();

    $folder = $folderHandler->getByID($folderId);

    if (!$folder) {
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
function getSingleFileMeta($folderId, $fileId)
{

    $fileHandler = Schoolbox\Resource\My\File\Mapper::getInstance();

    try {
        /** @var Schoolbox\Resource\My\File\Model $file */
        $file = $fileHandler->findById($fileId);
    } catch (Schoolbox\Mapper\Exception $e) {
        sendError('No file with the specified ID exists');
        exit;
    }

    // write access provides actions
    $actions = array('modify', 'delete', 'open', 'download');

    // list of portfolio pages that can be published to
    $availablePages = \Eportfolio\PageMapper::getInstance()->findPublishablePages($_SESSION['id']);
    $publishTo = array();

    foreach ($availablePages as $currentPage) {
        // Each link only needs to know this
        $pageData = array(
            'userId' => $_SESSION['id'],
            'pageId' => $currentPage['page']->getPageId(),
            'pageTitle' => $currentPage['page']->getPageTitle(),
        );
        $publishTo[] = $pageData;
    }

    $file = array(
        'kind' => 'file',
        'id' => $file->getId(),
        'name' => $file->getTitle(),
        'mime' => $file->getMimetype()->getIconFile(),
        'filesize' => Alaress_Byte_Format::bytesToString($file->getSize()),
        'posted_by' => 'you',
        'posted_date' => $file->getCreatedAt()->getDateOnly(),
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
function getMultiFileMeta($folderId)
{

    // if has write permission, grant delete access
    $groupHandler = new Group();
    $access = $groupHandler->getPermission($folderId);

    if ($access <= UserAccess::ACCESS_READ) {
        return array(
            'actions' => array()
        );
    } else {
        return array(
            'actions' => array('delete')
        );
    }
}
