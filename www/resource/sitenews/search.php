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
$newsHandler = new News();
$type = 'sitenews';

/* search() calls access a global counter array, $count
 */
$result[$type] = $newsHandler->search($keyword, ($current_page - 1) * Cfg::get('search_type_limit'), Cfg::get('search_type_limit'), $count[$type]);

$count['all'] = array_sum($count);

$result[$type] = $newsHandler->search($keyword, ($current_page - 1) * Cfg::get('search_type_limit'), Cfg::get('search_type_limit'), $count[$type]);

// render school news
if (!empty($result['sitenews'])) {
    $page->setFile('sitenews_t', 'resource/sitenews/search/results.html');
    $page->setBlock('sitenews_t', 'sitenews', 'sitenews_block');
    $page->setVar(array(
            'SITENEWS_DISP_COUNT' => count($result['news']),
            'SITENEWS_COUNT' => $count['news'],
        ));

    foreach ($result['sitenews'] as $news) {
        $now = new Datetime();
        $expiryDate = new DateTime($news['item_datetime_expires']);

        $tName = strtolower($news['topicName']);
        $archived = ($now->getTimestamp() > $expiryDate->getTimestamp());
        $news['breadcrumb'] = ($archived ? ' Archived' : 'Posted') . " in <a href=\"/news/" .
            ($archived ? 'archive/' : '') .
            "$tName\">".Scrub::text($news['topicName'])."</a>";

        $news['link'] = "/news/item/{$news['item_id']}";

        $userHandler = new User();
        $createdTime = new Alaress_Date_Relative($news['item_datetime_posted']);

        $page->setVar(array(
                'SITENEWS_ID' => $news['item_id'],
                'SITENEWS_CODE' => $news['code'],
                'SITENEWS_LINK' => $news['link'],
                'SITENEWS_HEADING' => Scrub::text($news['item_heading']),
                'SITENEWS_BODY' => TextFormatter::cutSentence(Scrub::html2text($news['item_body']), 150),
                'SITENEWS_OWNER' => $news['item_owner'],
                'SITENEWS_OWNER_NAME' => $userHandler->formatName($news),
                'SITENEWS_CREATED' => $createdTime->getRelativeTime(),
                'SITENEWS_BREADCRUMB' => $news['breadcrumb'],
                'SITENEWS_COUNT' => $count['news'],
            ));

        $page->parse('sitenews_block', 'sitenews', true);
    }

    outputSeeMore($page, $type, $count, 'sitenews');

    $page->parse('RESULT', 'sitenews_t', true);
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