<?php

require_once __DIR__ . '/../../conf/core/require-web.php';
// Open session after bootstrap, otherwise $_SESSION[user] won't be a user.
$page = new SitePage;

$treeBuilder = new WebdavTreeBuilder(PortfolioWebDAV::factory());
header('Content-Type: application/json');
echo json_encode($treeBuilder->buildChildNodes($_GET['relationID']));
