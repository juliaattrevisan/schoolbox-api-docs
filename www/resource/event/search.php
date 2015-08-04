<?php
/**
 * search.php
 *
 * Spawns search results for all minions
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once('../include/Bootstrap.php');

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
$result[$type] = $forumHandler->search($keyword, 0, Cfg::get('search_all_limit'), $count[$type]);

$count['all'] = array_sum($count);

/* You can save a db request by using the first $result[$type] and
 * traversing to $currentPage-1 manually in php, manually constructing
 * the page's contents
 */
$result[$type] = $forumHandler->search($keyword, ($current_page - 1) * Cfg::get('search_type_limit'), Cfg::get('search_type_limit'), $count[$type]);

$renderer = new \Schoolbox\Calendar\Event\Search\Renderer($page, $result, $count, $current_page, $keyword);
$renderer->render();
$renderer->renderPagination();

// render final output
$page->parse(TEMPLATE_body, 'RESULT', true);
$page->outputPage(true);

exit();
