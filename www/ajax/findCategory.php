<?php

// TODO move this to a proper controller
// It's only here because it already existed here (but is used nowhere)

use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Resource\Folder;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Kernel();

$page = new SitePage();
$categoryMapper = new Folder\Mapper();

$suggestions = array();
$data = array();

$allResults = $categoryMapper->searchByName($_GET['query']);

$return = array();

foreach ($allResults as $currentResult) {
    $return[] = array(
        'id'         => $currentResult->getId(),
        'name'       => $currentResult->getNameWithCodes(),
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
