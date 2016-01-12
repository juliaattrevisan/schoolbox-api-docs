<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';
$page = new SitePage(false);


$session = new \Session;
$session->create('anonymous',true);
$page->localRedir('/');