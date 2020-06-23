<?php

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');

$internalUrl = $_GET['page'] ?: '/';
if (Config::get('login_anonymous') && !$_SESSION['user']) {
    $session = new \Session;
    $session->create(Config::get('login_anonymous'), true);
    $page->localRedir($internalUrl);
} else {
    $page->localRedir($internalUrl);
}
