<?php

// TODO move this to a proper controller
// It's only here because it already existed here (but is used nowhere)

use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Menu\Side\Mapper;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Kernel();

$page = new SitePage();
$categoryMapper = new Folder\Mapper();

$suggestions = array();
$data = array();

$return = array();

/** @var User $user */
$user = $_SESSION['user'];

foreach ($_SESSION['subjects'] as $subject) {
    if ($subject['id']) {
        $return[] = array(
            'id'         => $subject['id'],
            'name'       => $subject['name'],
            'optgroup'   => 'class',
        );
    }
}

foreach (Mapper::getInstance()->findGroupsForUser($user) as $group) {
    $return[] = array(
        'id'         => $group['sid'],
        'name'       => $group['name'],
        'optgroup'   => 'group',
    );
}

foreach ($categoryMapper->searchByName($_GET['query']) as $currentResult) {
    $return[] = array(
        'id'         => $currentResult->getId(),
        'name'       => $currentResult->getNameWithCodes(),
        'optgroup'   => 'other',
        'breadcrumb' => $categoryMapper->breadcrumb($currentResult)->map(static function (Folder\Model $v) {
            return [
                'id'   => $v->getId(),
                'name' => $v->getNameWithCodes(),
            ];
        }),
    );
}

header('Content-type: application/json');
echo json_encode($return);
