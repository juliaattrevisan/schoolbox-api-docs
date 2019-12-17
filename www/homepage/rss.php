<?php

use Aura\Router\Router;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

// trim in case the leading slash is captured in the URI
$page = trim($_GET['page'], '/');
$args = explode('/', $page);

$catID = isset($args[2]) ? $args[2] : '';
$token = isset($args[3]) && !empty($args[3]) ? $args[3] : '';

/* Redirect to new module
 */
$di = $kernel->getDependencyInjector();
/** @var Router $router */
$router = $di->get('router');
$url = $catID
    ? $router->generate('commsNewsFeed.getFeedForFolder', ['folderId' => $catID])
    : $router->generate('commsNewsFeed.getFeedForUser', ['key' => $token]);

$page = new SitePage(false);
$page->localRedir($url, false, 308);
