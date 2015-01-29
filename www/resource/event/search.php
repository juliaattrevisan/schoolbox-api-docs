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
$forumHandler = new Calendar();
$type = 'event';

/* search() calls access a global counter array, $count
 */
$result[$type] = $forumHandler->search($keyword, 0, Cfg::get('search_all_limit'), $count[$type]);

$count['all'] = array_sum($count);

$result[$type] = $forumHandler->search($keyword, ($current_page - 1) * Cfg::get('search_type_limit'), Cfg::get('search_type_limit'), $count[$type]);

// render event
if (!empty($result['event'])) {
    $page->setFile('event_t', 'resource/event/search/results.html');
    $page->setBlock('event_t', 'event', 'event_block');
    $page->setVar(array(
            'EVENT_DISP_COUNT' => count($result['event']),
            'EVENT_COUNT' => $count['event'],
        ));

    foreach ($result['event'] as $event) {
        $date = new Alaress_Date_Format($event['startDate'], 'Y-m-d');
        $event_time = '';

        if (!empty($event['startTime']) && $event['startTime'] != '12:00AM') {
            $event_time .= $event['startTime'];

            if (!empty($event['endTime']) && $event['startTime'] != '12:00AM') {
                $event_time .= ' to ' . $event['endTime'];
            }
        }

        $page->setVar(array(
                'EVENT_TITLE' => Scrub::text($event['title']),
                'EVENT_TYPE' => Scrub::text($event['eventType']),
                'EVENT_DETAIL' => TextFormatter::cutSentence(Scrub::html2text($event['detail']),150),
                'EVENT_DATE' => $date->getDateOnly(),
                'EVENT_DATE_RAW' => $date->getUsingFormat('d-m-Y'),
                'EVENT_LOCATION' => $event['location'],
                'EVENT_TIME' => $event_time
            ));

        $page->parse('event_block', 'event', true);
    }

    outputSeeMore($page, $type, $count, 'event');

    $page->parse('RESULT', 'event_t', true);
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