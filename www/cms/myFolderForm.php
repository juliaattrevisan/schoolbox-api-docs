<?php

use Schoolbox\Resource\My\Folder as MyFolder;
use Schoolbox\Mapper;
use Schoolbox\Error;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();
$myFolderMapper = MyFolder\Mapper::getInstance();

if (isset($_POST['name'])) {
    // Create/Update
    if (is_numeric($_POST['folderID'])) {
        // check user owns folder
        $myFolder = $myFolderMapper->findById($_POST['folderID']);
        $id = $myFolder->getOwner()->getId();
        if ((int)$myFolder->getOwner()->getId() !== (int)$_SESSION['id']) {
            $page->error(Error\Mapper::ERROR_PERM_FILE_MODIFY);
        }
    } else {
        $myFolder = $myFolderMapper->createModel();
        $myFolder->setOwner($_SESSION['user'])
                 ->setParent($myFolderMapper->findById($_POST['parent']));
    }
    $myFolder->setName($_POST['name']);
    $myFolderMapper->save($myFolder);
    SitePage::localRedir("/my-files/{$myFolder->getId()}");
} elseif (is_numeric($_GET['delete'])) {
    // Delete
    try {
        $folder = $myFolderMapper->findById($_GET['delete']);
    } catch (Mapper\Exception $e) {
        $folder = null;
        error(2);
    }

    if ((int)$folder->getOwner()->getId() !== (int)$_SESSION['id']) {
        $page->error(Error\Mapper::ERROR_PERM_FILE_DELETE);
    }

    $myFolderMapper->delete($folder);

    if (isset($_REQUEST['ajax'])) {
        die(json_encode(['result' => 'success']));
    }
    SitePage::localRedir("/my-files/{$folder->getParent()->getId()}");
} elseif (is_numeric($_POST['move']) && is_numeric($_POST['categoryID'])) {
    // Move (via JS tree)
    $isAjax        = isset($_REQUEST['ajax']) && $_REQUEST['ajax'];
    $folderId      = (int)$_POST['move'];
    $destinationId = (int)$_POST['categoryID'];

    /* Move file from one category to another, after checking permission on both from and to categories */
    if ($folderId === $destinationId) {
        respondFailure($isAjax, "Cannot move folder to itself. ID: {$folderId}");
    }

    try {
        $myFolder = $myFolderMapper->findById($folderId);
    } catch (Mapper\Exception $e) {
        respondFailure($isAjax, 'Destination folder not found');
    }
    /** @var MyFolder\Model $myFolder */
    if ((int)$myFolder->getOwner()->getId() !== (int)$_SESSION['id']) {
        respondFailure($isAjax, 'Insufficient permissions');
    }
    $destination = $myFolderMapper->findById($destinationId);

    if ($myFolderMapper->isDescendant($destination, $myFolder)) {
        respondFailure($isAjax, 'Cannot move folder to own descendant');
    }
    $myFolder->setParent($destination);
    $myFolderMapper->save($myFolder);
} elseif (is_numeric($_POST['portfolioID'])) {
    $_SESSION['portfolioID'] = $_POST['portfolioID'];
}

/**
 * Send a failure response back to the browser and terminate script execution
 *
 * @param bool   $isAjax
 * @param string $message
 * @param int    $code
 */
function respondFailure($isAjax, $message, $code = 400)
{
    http_response_code($code);
    if ($isAjax) {
        header('Content-type:application/json');
        die(json_encode([
            'result'  => 'failure',
            'message' => $message,
        ]));
    }
    die($message);
}
