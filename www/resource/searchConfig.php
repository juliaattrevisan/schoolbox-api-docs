<?php

/* An include
 *
 * Shared configs for Search
 */

$page = new SitePage();
$group = new Group();
$categoryStd = new Category();
$allowedFile = new AllowedFile();

$handle = array(
    'user' => 'user',
    'file' => 'file',
    'category' => 'category',
    'sitenews' => 'news',
    'homepagenews' => 'categoryNews',
    'blog' => 'blog',
    'wiki' => 'wiki',
    'forum' => 'forum',
    'event' => 'calendar',
);

$type_list = array(
    'all' => 'All',
    'user' => 'People',
    'file' => 'Files',
    'category' => 'Folders',
    'sitenews' => 'School News',
    'homepagenews' => 'Homepage News',
    'blog' => 'Blogs',
    'wiki' => 'Wikis',
    'forum' => 'Forums',
    'event' => 'Calendar',
);

/* Omaha Beach
 * These handlers have been mapperised and shouldn't appear in the
 * old search.php contexts
 */
$omaha = array(
    'link'
);

/* Request params
 * strip out any injection attempts
 */
$keyword = trim(preg_replace('/[^\ A-Za-z0-9\"\'\+\-_=\@\?!\.]/', '', $_GET['keyword']));
$type = trim(preg_replace('/[^\ A-Za-z0-9\"\'\+\-_=\@\?!\.]/', '', $_GET['type']));
$current_page = empty($_GET['page']) ? 1 : (int)$_GET['page'];
$count = array();