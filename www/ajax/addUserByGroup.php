<?php

use Schoolbox\MapperFactory;
use Schoolbox\RoleMapper;
use Schoolbox\Resource\Folder;

require_once dirname(__FILE__) . '/../../library/Bootstrap.php';
require_once SITE_root . '/www/include/stdfuncs.php';

/**
 * @var MapperFactory $mapperFactory
 * @var RoleMapper    $roleMapper
 * @var Folder\Mapper $folderMapper
 */
$mapperFactory = $kernel->getDependencyInjector()->get('mapperFactory');
$roleMapper    = $mapperFactory->get(RoleMapper::class);
$folderMapper  = $mapperFactory->get(Folder\Mapper::class);
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
