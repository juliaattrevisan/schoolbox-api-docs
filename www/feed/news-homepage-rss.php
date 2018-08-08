<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/Bootstrap.php';

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
$router = $di->get('router');
$url = $router->generate('commsNewsFeed.getFeed', ['key' => $userKey, 'folderId' => $folderId]);

/* Redirect to new module
 */
$page->localRedir($url, false, 308);

