<?php

// TODO move this to a proper controller
// It's only here because it already existed here (but is used nowhere)

use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Menu\Side\Mapper as SideMenuMapper;
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
$user = $_SESSION['user'];

// My classes
// Just return them all: selectize will filter them
foreach ($_SESSION['subjects'] as $subject) {
    if ($subject['id']) {
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
    $return[$group['sid']] = [
        'id'       => $group['sid'],
        'name'     => $group['name'],
        'optgroup' => 'group',
    ];
}

// Other folders
foreach ($categoryMapper->searchByName($_GET['query']) as $currentResult) {
    /** @var Folder\Model $currentResult */
    $folderId = $currentResult->getId();
    if (isset($return[$folderId])) {
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
