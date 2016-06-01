<?php

require_once __DIR__ . '/../../library/Bootstrap.php';
// Open session after bootstrap, otherwise $_SESSION[user] won't be a user.
$page = new SitePage;

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
