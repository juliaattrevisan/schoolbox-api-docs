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
$categoryNewsHandler = new CategoryNews();
$type = 'homepagenews';

/* search() calls access a global counter array, $count
 */
$result[$type] = $categoryNewsHandler->search($keyword, ($current_page - 1) * Cfg::get('search_type_limit'), Cfg::get('search_type_limit'), $count[$type]);

$count['all'] = array_sum($count);

$result[$type] = $categoryNewsHandler->search($keyword, ($current_page - 1) * Cfg::get('search_type_limit'), Cfg::get('search_type_limit'), $count[$type]);

// render homepage news
if (!empty($result['homepagenews'])) {
    $page->setFile('homepagenews_t', 'resource/homepagenews/search/results.html');
    $page->setBlock('homepagenews_t', 'homepagenews', 'homepagenews_block');
    $page->setVar(array(
            'HOMEPAGE_NEWS_DISP_COUNT' => count($result['homepagenews']),
            'HOMEPAGE_NEWS_COUNT' => $count['homepagenews'],
        ));

    foreach ($result['homepagenews'] as $news) {
        foreach ($categoryStd->breadcrumb($news['code']) as $i => $breadcrumb) {
            $news['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";
        }

        if (new DateTime() > new DateTime($news['datetime_expires'])) {
            $news['link'] = "/homepage/newsArchive/{$news['code']}#{$news['id']}";
        } else {
            $news['link'] = "/homepage/{$news['code']}#news-{$news['id']}";
        }

        $userHandler = new User();
        $createdTime = new Alaress_Date_Relative($news['datetime_posted']);

        $page->setVar(array(
                'HOMEPAGENEWS_ID' => $news['id'],
                'HOMEPAGENEWS_CODE' => $news['code'],
                'HOMEPAGENEWS_LINK' => $news['link'],
                'HOMEPAGENEWS_HEADING' => Scrub::text($news['heading']),
                'HOMEPAGENEWS_BODY' => TextFormatter::cutSentence(Scrub::html2text($news['body']), 150),
                'HOMEPAGENEWS_OWNER' => $news['uid'],
                'HOMEPAGENEWS_OWNER_NAME' => $userHandler->formatName($news),
                'HOMEPAGENEWS_CREATED' => $createdTime->getRelativeTime(),
                'HOMEPAGENEWS_BREADCRUMB' => $news['breadcrumb'],
                'HOMEPAGENEWS_COUNT' => $count['homepagenews'],
            ));

        $page->parse('homepagenews_block', 'homepagenews', true);
    }

    outputSeeMore($page, $type, $count, 'homepagenews');

    $page->parse('RESULT', 'homepagenews_t', true);
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