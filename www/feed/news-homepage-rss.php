<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Aura\Router\Router;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

$page = new SitePage(false);

$user = null;
$folder = null;

/* Param explosion
 */
$params = parse_url($_SERVER['QUERY_STRING']);

preg_match('/user=([a-zA-Z0-9]+)(&category=)?(\d*)/', $params['path'], $matches);

$userKey = $matches[1];
$folderId = $matches[3];

/* Redirect to new module
 */
$di = $kernel->getDependencyInjector();
/** @var Router $router */
$router = $di->get('router');
$url = $folderId
    ? $router->generate('commsNewsFeed.getFeedForFolder', ['folderId' => $folderId])
    : $router->generate('commsNewsFeed.getFeedForUser', ['key' => $userKey]);


/* Redirect to new module
 */
$page->localRedir($url, false, 308);

