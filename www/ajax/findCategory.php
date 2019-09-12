<?php

// TODO move this to a proper controller
// It's only here because it already existed here (but is used nowhere)

use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Menu\Side\Mapper as SideMenuMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Kernel();

$page           = new SitePage();
$categoryMapper = new Folder\Mapper();

$suggestions = [];
$data        = [];

$return = [];

/** @var User $user */
$user   = $_SESSION['user'];
$access = Access::getInstance();
$query  = $_GET['query'];

// My classes
foreach ($_SESSION['subjects'] as $subject) {
    if ($subject['id'] && mb_stripos($subject['name'], $query) !== false) {
        $return[$subject['id']] = [
            'id'       => $subject['id'],
            'name'     => $subject['name'],
            'optgroup' => 'class',
        ];
    }
}

// My groups
// As with classes, just return them all
foreach (SideMenuMapper::getInstance()->findGroupsForUser($user) as $group) {
    if (isset($return[$group['sid']])) {
        continue;
    }
    if (mb_stripos($group['name'], $query) !== false) {
        $return[$group['sid']] = [
            'id'       => $group['sid'],
            'name'     => $group['name'],
            'optgroup' => 'group',
        ];
    }
}

// Other folders
foreach ($categoryMapper->searchByName($query) as $currentResult) {
    /** @var Folder\Model $currentResult */
    $folderId = $currentResult->getId();
    if (isset($return[$folderId])) {
        continue;
    }

    // Verify user has permission to view this folder
    if ($access->getFolderAccess($user, $folderId) < UserAccess::ACCESS_READ) {
        continue;
    }

    // Optgroup: put into a list of other classes or groups if required
    // Breadcrumb only for other folders which aren't groups or classes:
    // classes have unique codes, groups should have uniquish names
    if ($currentResult->isClass()) {
        $optgroup   = 'otherClass';
        $breadcrumb = null;
    } elseif ($currentResult->isGroup()) {
        $optgroup   = 'otherGroup';
        $breadcrumb = null;
    } else {
        $optgroup   = 'otherFolder';
        $breadcrumb = $categoryMapper->breadcrumb($currentResult)->map(static function (Folder\Model $v) {
            return [
                'id'   => $v->getId(),
                'name' => $v->getNameWithCodes(),
            ];
        });
    }

    $return[$folderId] = [
        'id'         => $folderId,
        'name'       => $currentResult->getNameWithCodes(),
        'optgroup'   => $optgroup,
        'breadcrumb' => $breadcrumb,
    ];
}

header('Content-type: application/json');
echo json_encode(array_values($return));
