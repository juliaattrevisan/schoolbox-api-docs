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
/** @var SitePage $this */
$page = $this;
$page->injectGlobalVars();

$handle = [
    'all' => [
        'handler' => null,
        'renderer' => null,
    ],
    'user' => [
        'handler' => new User(),
        'renderer' => new \Schoolbox\User\Search\Renderer($this, [], [], 0),
    ],
    'blog' => [
        'handler' => new Blog(),
        'renderer' => new \Schoolbox\Blog\Homepage\Search\Renderer($this, [], [], 0),
    ],
    'wiki' => [
        'handler' => new Wiki(),
        'renderer' => new \Schoolbox\Wiki\Search\Renderer($this, [], [], 0),
    ],
    'event' => [
        'handler' => new Calendar(),
        'renderer' => new \Schoolbox\Calendar\Event\Search\Renderer($this, [], [], 0),
    ],
];

$type_list = [
    'all' => 'All',
    'user' => 'People',
    'file' => 'Files',
    'blog' => 'Blogs',
    'wiki' => 'Wikis',
    'forum' => 'Forums',
    'event' => 'Calendar',
];

/* Omaha Beach
 * These handlers have been mapperised and shouldn't appear in the
 * old search.php contexts
 */
$omaha = [
    'folder',
    'file',
    'link',
    'textbox',
    'news',
    'forum'
];

// Order of results, independent of whether it has landed on Omaha or not
$order = [
    'user',
    'file',
    'category',
    'link',
    'news',
    'blog',
    'wiki',
    'forum',
    'event',
];
