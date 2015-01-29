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

/**
 * @param SitePage $page
 * @param string $type
 * @param array $count
 * @param string $handle
 *
 * @return void
 */
function outputSeeMore($page, $type, $count, $handle)
{
    $page->setBlock($handle . '_t', 'see_more', 'see_more_block');

    if ($count[$type] > Cfg::get('search_all_limit')) {
        $page->setVar('MORE_COUNT', $count[$type]);
        $page->parse('see_more_block', 'see_more');
    } else {
        $page->setVar('see_more_block', '');
    }
}
