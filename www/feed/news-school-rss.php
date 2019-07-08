<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use Schoolbox\News\TopicMapper;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

$page = new SitePage(false);

$user = null;
$topic = null;

/* Param explosion
 */
$params = parse_url($_SERVER['QUERY_STRING']);

preg_match('/user=([a-zA-Z0-9]+)(&topic=)?(\d*)/', $params['path'], $matches);

$userKey = $matches[1];
$topicId = $matches[3];

/* Redirect to new module
 */
$di = $kernel->getDependencyInjector();
$router = $di->get('router');
$baseUrl = $router->generate('commsNewsFeed.getFeed', ['key' => $userKey]);

if (\strlen($topicId)) {
    try {
        $topic = TopicMapper::getInstance()->findById($topicId);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(\Schoolbox\Error\Mapper::ERROR_COMMS_NEWS_INVALID_TOPIC);
    }

    $page->localRedir("{$baseUrl}?topic={$topic->getSlug()}", false, 308);
}

/* Redirect to All Topics url
 */
$topicSlug = \Schoolbox\Comms\News\Mapper::TOPIC_ALL_SLUG;
$page->localRedir("{$baseUrl}?topic={$topicSlug}", false, 308);
