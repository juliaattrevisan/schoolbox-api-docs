<?php
/**
 * search.php
 *
 * Spawns search results for this particular minion
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/* Old handler junk
 */
require_once('./resource/searchConfig.php');

/* Clean params
 */
require_once('./resource/searchClean.php');

$type = 'folder';

/* Omaha Beach!
 *
 * These have landed
 * When you add more, remove them from the searchConfig pseudo-registry
 *
 * Everything here is self-contained
 */
$articleResolver = $this->getDi()->get('commsNewsArticleResolver');

$mapper = new \Schoolbox\Resource\Search\Results($page, $type);
$mapper->search(
    $articleResolver,
    $_SESSION['user'],
    $keyword,
    ($current_page - 1) * Config::get('search_type_limit'),
    Config::get('search_type_limit'),
    'score',
    'desc'
);
$mapper->render();
$mapper->renderPagination($current_page);

$page->outputPage(true);
exit();
