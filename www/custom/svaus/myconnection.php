<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

if (isset($this)) {
    /** @var SitePage $this */
    $this->pageNotFound();
}

$titleToSearch = 'connection';

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel       = new Kernel();
$user         = $_SESSION['user'];
$folderMapper = FolderMapper::getInstance();
$pb           = $folderMapper->getPredicateBuilder()
                             ->like('name', "%{$titleToSearch}%");

$folders = $folderMapper->findByPredicates($pb)->sortNaturally('name')->getPropertyValues('id');

foreach ($folders as $folderId) {
    $access = Access::getInstance()->getAccessLevel(
        $user,
        UserAccess::SERVICE_HOMEPAGE, $folderId
    );
    if ($access >= UserAccess::ACCESS_READ) {
        SitePage::localRedir('/homepage/' . $folderId);
        break;
    }
}
error(ErrorMapper::ERROR_PAGE_NOT_FOUND);
exit;
