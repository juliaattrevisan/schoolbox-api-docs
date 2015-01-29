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
$categoryHandler = new Category();
$type = 'category';

/* search() calls access a global counter array, $count
 */
$result[$type] = $categoryHandler->search($keyword, ($current_page - 1) * Cfg::get('search_type_limit'), Cfg::get('search_type_limit'), $count[$type], 'id', 'desc');

$count['all'] = array_sum($count);

$result[$type] = $categoryHandler->search($keyword, ($current_page - 1) * Cfg::get('search_type_limit'), Cfg::get('search_type_limit'), $count[$type]);

// render categories
if (!empty($result['category'])) {
    $page->setFile('category_t', 'resource/folder/search/results.html');
    $page->setBlock('category_t', 'category', 'category_block');
    $page->setBlock('category', 'modify', 'modify_block');
    $page->setVar(array(
            'CATEGORY_DISP_COUNT' => count($result['category']),
            'CATEGORY_COUNT' => $count['category'],
        ));

    foreach ($result['category'] as $category) {
        foreach ($categoryStd->breadcrumb($category['id']) as $i => $breadcrumb)
            $category['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";

        $page->setVar(array(
                'CATEGORY_ID' => $category['id'],
                'CATEGORY_NAME' => $category['code'] ? Scrub::text($category['name']) . '(' . $category['code'] . ')' : Scrub::text($category['name']),
                'CATEGORY_CODE' => $category['code'],
                'CATEGORY_BREADCRUMB' => $category['breadcrumb'],
                'CATEGORY_COUNT' => $count['category'],
                'CATEGORY_TYPE' => ($category['template'] ? 'home' : 'folder'),
            ));

        if ($category['perms_type'] < 2 && $category['userAccess_type'] < 2)
            $page->setVar('modify_block');
        else
            $page->parse('modify_block', 'modify');

        $page->parse('category_block', 'category', true);
    }

    outputSeeMore($page, $type, $count, 'category');

    $page->parse('RESULT', 'category_t', true);
}

/* Pagination
 */
// clear count
$page->setBlock('RESULT', 'count', 'count_block');
$page->setVar('count_block');

// render pagination
$page->setBlock('RESULT', 'pagination', 'pagination_block');

$number_pages = ceil($count[$type] / Cfg::get('search_type_limit'));

if ($number_pages > 1) {
    if (($current_page - 5) <= 1) {
        $start_window = 1;
        $end_window = $number_pages > 10 ? 10 : $number_pages;
    } elseif (($current_page + 5) >= $number_pages) {
        $start_window = $number_pages - 10;
        $end_window = $number_pages;
    } else {
        $start_window = $current_page - 5;
        $end_window = $current_page + 5;
    }
    //
    // Limit start and end (start can't be < 1, end can't be > num pages)
    $start_window = max($start_window, 1);
    $end_window   = min($end_window, $number_pages);

    $page->setVar(array(
            'PAGE_NAME' => '<',
            'PAGE_NUMBER' => '1',
            'PAGE_CLASS' => '',
        ));

    $page->parse('pagination_block', 'pagination', true);

    for ($i = $start_window; $i <= $end_window; $i++) {
        $page->setVar(array(
                'PAGE_NAME' => $i,
                'PAGE_NUMBER' => $i,
                'PAGE_CLASS' => $i == $current_page ? 'current' : '',
            ));

        $page->parse('pagination_block', 'pagination', true);
    }

    $page->setVar(array(
            'PAGE_NAME' => '>',
            'PAGE_NUMBER' => $number_pages,
            'PAGE_CLASS' => '',
        ));

    $page->parse('pagination_block', 'pagination', true);

    // only 1 page to display
} else {
    $page->setVar('pagination_block');
}

// render final output
$page->parse(TEMPLATE_body, 'RESULT', true);
$page->outputPage(true);

exit();