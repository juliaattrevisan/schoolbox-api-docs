<?php
/**
 * search.php
 *
 * Spawns search results for this particular minion
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

$type = 'link';

/* Omaha Beach!
 */
$mapper = new \Schoolbox\Resource\Search\Results($page, $type, $keyword);
$mapper->search($_SESSION['user'], $keyword, ($current_page - 1) * Cfg::get('search_type_limit'), Cfg::get('search_type_limit'));

/* Omaha Beach
 *
 * These have landed
 * When you add more, remove them from the searchConfig pseudo-registry
 * and remove them from the gigantic phplibbery below
 *
 * Everything here is self-contained
 */
$mapper->render();
$mapper->renderPagination($current_page);

exit();