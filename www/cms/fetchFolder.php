<?php
/**
 * Script to put all resources from a folder into a zip and send to client.
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Storage\Zip\Folder;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

$page = new SitePage();
try {
    $folder = FolderMapper::getInstance()->findById($_GET['folder']);
} catch (Schoolbox\Mapper\Exception $e) {
    error(ErrorMapper::ERROR_PERM_DOWNLOAD_FILE);
}

$access = Access::getInstance();
if ($access->getFolderAccess($_SESSION['user'], $folder->getId()) > UserAccess::ACCESS_READ) {
    error(ErrorMapper::ERROR_CATEGORY_DELETED);
}

$zip = new Folder($folder);
//ShutdownHandler::instance()->registerFunction([$zip, 'cleanUp'], 10000);
$zip->sendZip();
