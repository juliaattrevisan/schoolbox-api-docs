<?php

require_once __DIR__ . '/../../include/Bootstrap.php';
// Open session after bootstrap, otherwise $_SESSION[user] won't be a user.
session_start();

$treeBuilder = new WebdavTreeBuilder(PortfolioWebDAV::factory());
header('Content-Type: application/json');
echo json_encode($treeBuilder->buildChildNodes($_GET['relationID']));
