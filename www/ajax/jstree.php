<?php

session_start();
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Assessment\Course\TaskMapper;

$treeBuilder = new Schoolbox\Resource\CategoryTree(
    CategoryMapper::getInstance(),
    TaskMapper::getInstance(),
    new Group()
);

header('Content-Type: application/json');
echo json_encode($treeBuilder->buildChildNodes($_GET['parent']));
