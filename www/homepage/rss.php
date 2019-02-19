<?php

$kernel = require_once $_SERVER['DOCUMENT_ROOT'] . '/../conf/core/require-web.php';

// trim in case the leading slash is captured in the URI
$page = trim($_GET['page'], '/');
$args = explode('/', $page);

$catID = isset($args[2]) ? $args[2] : '';
$token = isset($args[3]) && !empty($args[3]) ? $args[3] : '';

/* Redirect to new module
 */
$di = $kernel->getDependencyInjector();
$router = $di->get('router');
$url = $router->generate('commsNewsFeed.getFeed', ['key' => $token, 'folderId' => $catID]);

$page = new SitePage(false);
$page->localRedir($url, false, 308);
