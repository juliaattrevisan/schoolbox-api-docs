<?php
/**
 * An include
 *
 * Shared configs for Search
 */
define('MIN_KEYWORD_LENGTH', 2);
define('AJAX_ENABLED', 1);
define('AJAX_DISABLED', 0);

// Always use the sitepage from DI
/** @var SitePage $page */
$page = $this;
$page->localise();

$handle = array(
    'all' => array(
        'handler' => null,
        'renderer' => null,
    ),
    'user' => array(
        'handler' => new User(),
        'renderer' => new \Schoolbox\User\Search\Renderer($this, '', '', 0, $keyword),
    ),
//    'file' => array(
//        'handler' => new File(),
//        'renderer' => new \Schoolbox\File\Search\Renderer($this, '', '', 0, $keyword),
//    ),
    'sitenews' => array(
        'handler' => new News(),
        'renderer' => new \Schoolbox\News\Search\Renderer($this, '', '', 0, $keyword),
    ),
    'homepagenews' => array(
        'handler' => new CategoryNews(),
        'renderer' => new \Schoolbox\Folder\News\Search\Renderer($this, '', '', 0, $keyword),
    ),
    'blog' => array(
        'handler' => new Blog(),
        'renderer' => new \Schoolbox\Blog\Search\Renderer($this, '', '', 0, $keyword),
    ),
    'wiki' => array(
        'handler' => new Wiki(),
        'renderer' => new \Schoolbox\Wiki\Search\Renderer($this, '', '', 0, $keyword),
    ),
    'forum' => array(
        'handler' => new Forum(),
        'renderer' => new \Schoolbox\Forum\Search\Renderer($this, '', '', 0, $keyword),
    ),
    'event' => array(
        'handler' => new Calendar(),
        'renderer' => new \Schoolbox\Calendar\Event\Search\Renderer($this, '', '', 0, $keyword),
    ),
);

$type_list = array(
    'all' => 'All',
    'user' => 'People',
    'file' => 'Files',
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
    'folder', 'file', 'link', 'textbox',
);

// Order of results, independent of whether it has landed on Omaha or not
$order = [
    'user',
    'file',
    'category',
    'link',
    'sitenews',
    'homepagenews',
    'blog',
    'wiki',
    'forum',
    'event',
];
