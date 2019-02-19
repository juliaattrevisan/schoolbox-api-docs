<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../conf/core/require-web.php';
$page = new SitePage(false);

$internalUrl = $_GET['page'] ?: '/';
if (Cfg::get('login_anonymous') && !$_SESSION['user']) {
    $session = new \Session;
    $session->create(Cfg::get('login_anonymous'), true);
    $page->localRedir($internalUrl);
} else {
    $page->localRedir($internalUrl);
}
