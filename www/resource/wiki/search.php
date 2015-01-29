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
$wikiHandler = new Wiki();
$type = 'wiki';

/* search() calls access a global counter array, $count
 */
$result[$type] = $wikiHandler->search($keyword, 0, Cfg::get('search_all_limit'), $count[$type]);

$count['all'] = array_sum($count);

$result[$type] = $wikiHandler->search($keyword, ($current_page - 1) * Cfg::get('search_type_limit'), Cfg::get('search_type_limit'), $count[$type]);

// render wiki
if (!empty($result['wiki'])) {
    $page->setFile('wiki_t', 'resource/wiki/search/results.html');
    $page->setBlock('wiki_t', 'wiki', 'wiki_block');
    $page->setVar(array(
            'WIKI_DISP_COUNT' => count($result['wiki']),
            'WIKI_COUNT' => $count['wiki'],
        ));

    $userHandler = new User();

    foreach ($result['wiki'] as $wiki) {
        foreach ($categoryStd->breadcrumb($wiki['categoryID']) as $i => $breadcrumb) {
            $wiki['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";
        }

        $createdTime = new Alaress_Date_Relative($wiki['created']);

        $page->setVar(array(
                'WIKI_ID' => $wiki['wikiID'],
                'WIKI_CID' => $wiki['categoryID'],
                'WIKI_HEADING' => Scrub::text($wiki['heading']),
                'WIKI_BODY' => TextFormatter::cutSentence(Scrub::html2text($wiki['body']), 150),
                'WIKI_OWNER' => $wiki['userID'],
                'WIKI_OWNER_NAME' => $userHandler->formatName($wiki),
                'WIKI_CREATED' => $createdTime->getRelativeTime(),
                'WIKI_BREADCRUMB' => $wiki['breadcrumb'],
                'WIKI_COUNT' => $count['wiki'],
                'WIKI_SLUG' => $wiki['slug'],
            ));

        $page->parse('wiki_block', 'wiki', true);
    }

    outputSeeMore($page, $type, $count, 'wiki');

    $page->parse('RESULT', 'wiki_t', true);
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