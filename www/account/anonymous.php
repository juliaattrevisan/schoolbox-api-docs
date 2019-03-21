<?php

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();
$page = new SitePage(false);

$internalUrl = $_GET['page'] ?: '/';
if (Cfg::get('login_anonymous') && !$_SESSION['user']) {
    $session = new \Session;
    $session->create(Cfg::get('login_anonymous'), true);
    $page->localRedir($internalUrl);
} else {
    $page->localRedir($internalUrl);
}
