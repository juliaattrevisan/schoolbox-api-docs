<?php
/**
 * search.php
 *
 * Spawns search results for all minions
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

/**
 * Execute searches for all handlers and mappers
 */
$forumHandler = new Calendar();
$type = 'event';

/* search() calls access a global counter array, $count
 */
$result[$type] = $forumHandler->search($keyword, 0, Config::get('search_all_limit'), $count[$type]);

$count['all'] = array_sum($count);

/* You can save a db request by using the first $result[$type] and
 * traversing to $currentPage-1 manually in php, manually constructing
 * the page's contents
 */
$result[$type] = $forumHandler->search($keyword, ($current_page - 1) * Config::get('search_type_limit'), Config::get('search_type_limit'), $count[$type]);

$renderer = new \Schoolbox\Calendar\Event\Search\Renderer($page, $result, $count, $current_page);
$renderer->render();
$renderer->renderPagination();

// render final output
$page->parse(TEMPLATE_content, 'RESULT', true);
$page->outputPage(true);

exit();
