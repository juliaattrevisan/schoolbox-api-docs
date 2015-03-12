<?php

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';
// Open session after bootstrap, otherwise $_SESSION[user] won't be a user.
$page = new SitePage;

use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Assessment\Course\TaskMapper;

$treeBuilder = new Folder\TreeBuilder(
    Folder\Mapper::getInstance(),
    TaskMapper::getInstance(),
    UserAccessMapper::getInstance(),
    RoleAccessMapper::getInstance(),
    $_SESSION['user']
);

header('Content-Type: application/json');
echo json_encode($treeBuilder->buildChildNodes($_GET['parent']));
