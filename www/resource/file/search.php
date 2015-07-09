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
require_once(__DIR__ . '/../../../include/Bootstrap.php');

/* Old handler junk
 */
require_once(__DIR__ . '/../../resource/searchConfig.php');

/* Clean params
 */
require_once(__DIR__ . '/../../resource/searchClean.php');

$type = 'file';

/* Omaha Beach!
 *
 * These have landed
 * When you add more, remove them from the searchConfig pseudo-registry
 *
 * Everything here is self-contained
 */
$mapper = new \Schoolbox\Resource\Search\Results($page, $type, $keyword);
$mapper->search(
    $_SESSION['user'],
    $keyword,
    ($current_page - 1) * Cfg::get('search_type_limit'),
    Cfg::get('search_type_limit'),
    'score',
    'desc'
);
$mapper->render();
$mapper->renderPagination($current_page);

$page->outputPage(true);
exit();