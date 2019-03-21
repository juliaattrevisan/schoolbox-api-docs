<?php

use Schoolbox\MapperFactory;
use Schoolbox\RoleMapper;
use Schoolbox\Resource\Folder;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var RoleMapper $roleMapper */
$roleMapper = RoleMapper::getInstance();
/** @var Folder\Mapper $folderMapper */
$folderMapper = Folder\Mapper::getInstance();

$return        = [];

if ($_GET['type'] === 'system') {
    /* Add from this role...
     */
    foreach ($roleMapper->findByName($_GET['query']) as $role) {
        $return[] = [
            'name' => $role->getName(),
            'id'   => $role->getId(),
        ];
    }
} else {
    /* Add from this group...
     */
    foreach ($folderMapper->searchByName($_GET['query'], true) as $group) {
        $return[] = [
            'name' => $group->getName(),
            'id'   => $group->getId(),
        ];
    }
}

/* Accumulate results
 */

header('Content-type: application/json');
echo json_encode($return);
