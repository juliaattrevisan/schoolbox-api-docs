<?php
/**
 * An include
 *
 * Shared configs for Search
 */
define('MIN_KEYWORD_LENGTH', 2);
define('AJAX_ENABLED', 1);
define('AJAX_DISABLED', 0);

$page = new SitePage();

$handle = array(
    'user' => 'User',
    'file' => 'File',
    'category' => 'Category',
    'sitenews' => 'News',
    'homepagenews' => 'CategoryNews',
    'blog' => 'Blog',
    'wiki' => 'Wiki',
    'forum' => 'Forum',
    'event' => 'Calendar',
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

