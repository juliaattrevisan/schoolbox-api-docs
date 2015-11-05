<?php

/**
 * @deprecated this file is deprecated in favour of Schoolbox\Resource\Browser\AbstractController::listNodes()
 */

/** @var Kernel $kernel */
$kernel = require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

/** @var SitePage $page */
$page = $kernel->getDependencyInjector()->get('sitePage');

use Alaress\Application\Kernel;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;

$treeBuilder = new \Schoolbox\Resource\Browser\Resources\TreeBuilder(
    Folder\Mapper::getInstance(),
    TaskMapper::getInstance(),
    UserAccessMapper::getInstance(),
    RoleAccessMapper::getInstance(),
    $_SESSION['user'],
    $page->getLocalisedTerm('resource', true, true)
);

header('Content-Type: application/json');
echo json_encode($treeBuilder->buildChildNodes($_GET['parent']));
