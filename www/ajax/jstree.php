<?php

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';
// Open session after bootstrap, otherwise $_SESSION[user] won't be a user.
session_start();

use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Assessment\Course\TaskMapper;

$treeBuilder = new Schoolbox\Resource\CategoryTreeBuilder(
    CategoryMapper::getInstance(),
    TaskMapper::getInstance(),
    new Group()
);

header('Content-Type: application/json');
echo json_encode($treeBuilder->buildChildNodes($_GET['parent']));
