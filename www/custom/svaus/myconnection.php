<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;

if (isset($this)) {
    /** @var SitePage $this */
    $this->pageNotFound();
}

$titleToSearch = 'My Connection';

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel       = new Kernel();
$user         = $_SESSION['user'];
$folderMapper = FolderMapper::getInstance();
$groupFolders = $folderMapper->findForUser($user, false, UserAccess::ACCESS_READ, false, true)
                             ->sortNaturally('name')
                             ->filter(
                                 static function (Folder $folder) use ($titleToSearch): bool {
                                     return strpos($folder->getName(), $titleToSearch) !== false;
                                 }
                             );
if ($groupFolders->first()) {
    SitePage::localRedir('/homepage/' . $groupFolders->first()->getId());
}
error(ErrorMapper::ERROR_PAGE_NOT_FOUND);
exit;
