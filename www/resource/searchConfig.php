<?php
/**
 * An include
 *
 * Shared configs for Search
 */
define('MIN_KEYWORD_LENGTH', 2);
define('AJAX_ENABLED', 1);
define('AJAX_DISABLED', 0);
//'blog' =>
//        'file' =>
//        'category' =>
//        'event' =>
//        'homepagenews' =>
//        'sitenews' =>
//        'wiki' =>
//        'forum' =>
$page = new SitePage();

$handle = array(
    'all' => array(
        'handler' => null,
        'renderer' => null,
    ),
    'user' => array(
        'handler' => new User(),
        'renderer' => new \Schoolbox\User\Search\Renderer($this, '', '', 0),
    ),
    'file' => array(
        'handler' => new File(),
        'renderer' => new \Schoolbox\File\Search\Renderer($this, '', '', 0),
    ),
    'category' => array(
        'handler' => new Category(),
        'renderer' => new \Schoolbox\Folder\Search\Renderer($this, '', '', 0),
    ),
    'sitenews' => array(
        'handler' => new News(),
        'renderer' => new \Schoolbox\News\Search\Renderer($this, '', '', 0),
    ),
    'homepagenews' => array(
        'handler' => new CategoryNews(),
        'renderer' => new \Schoolbox\Folder\News\Search\Renderer($this, '', '', 0),
    ),
    'blog' => array(
        'handler' => new Blog(),
        'renderer' => new \Schoolbox\Blog\Search\Renderer($this, '', '', 0),
    ),
    'wiki' => array(
        'handler' => new Wiki(),
        'renderer' => new \Schoolbox\Wiki\Search\Renderer($this, '', '', 0),
    ),
    'forum' => array(
        'handler' => new Forum(),
        'renderer' => new \Schoolbox\Forum\Search\Renderer($this, '', '', 0),
    ),
    'event' => array(
        'handler' => new Calendar(),
        'renderer' => new \Schoolbox\Calendar\Event\Search\Renderer($this, '', '', 0),
    ),
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

