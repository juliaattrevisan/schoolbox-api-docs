<?php

require_once __DIR__ . '/../../include/Bootstrap.php';
// Open session after bootstrap, otherwise $_SESSION[user] won't be a user.
session_start();

use Schoolbox\User;
use Schoolbox\MyFolderMapper;
use Schoolbox\MyFolderTreeBuilder;

$output = array();
if ($_SESSION['user'] instanceof User) {
    $treeBuilder = new MyFolderTreeBuilder(MyFolderMapper::getInstance(), $_SESSION['user']);
    $output      = $treeBuilder->buildChildNodes($_GET['relationID']);
}
header('Content-Type: application/json');
echo json_encode($output);
