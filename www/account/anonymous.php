<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';
$page = new SitePage(false);


if(Cfg::get('login_anonymous')) {
    $session = new \Session;
    $session->create(Cfg::get('login_anonymous'), true);
    $page->localRedir('/');
}
else {
    $page->localRedir('/');
}