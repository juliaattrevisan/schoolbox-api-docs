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

// iterate through the handle types
foreach ($handle as $type_name => &$type_handle) {
    // include & instantiate
    $type_class = ucfirst($type_handle);
    $type_handle = new $type_class;

    /* search() calls access a global counter array, $count
     */
    $result[$type_name] = $type_handle->search($keyword, 0, Cfg::get('search_all_limit'), $count[$type_name]);
}

$count['all'] = array_sum($count);

/* Omaha Beach!
 */
$mapper = new \Schoolbox\Resource\Search\Results($page, $type);
$mapper->search( $_SESSION['user'], $keyword, 0, Cfg::get('search_all_limit'));

/* Omaha Beach
 *
 * There's a sequential dependency here, which prevents us from moving
 * up the beach in a nice way. 2RS uses $count as a global var, a terrible
 * move, and it's even more annoying to use a $temp var to counter it
 */
$count['all'] += $mapper->countTotalResults();

/* Omaha Beach
 *
 * These have landed
 * When you add more, remove them from the searchConfig pseudo-registry
 * and remove them from the gigantic phplibbery below
 *
 * Everything here is self-contained
 */
if ($type == 'all') {
    $mapper->render();
}

/* These are old handlers
 */
if (!empty($handle[$type])) {
    // include & instantiate
    $type_class = ucfirst($handle[$type]);
    $handle[$type] = new $type_class;

    /* Particular ordering and sorting for Categories
     */
    if (in_array($type_class, array('Category'))) {
        $result[$type] = $handle[$type]->search($keyword, ($current_page - 1) * Cfg::get('search_type_limit'), Cfg::get('search_type_limit'), $count[$type], 'id', 'desc');
    } else {
        // perform search for individual category (without formatting for ajax call)
        $result[$type] = $handle[$type]->search($keyword, ($current_page - 1) * Cfg::get('search_type_limit'), Cfg::get('search_type_limit'), $count[$type]);
    }
}

$renderer = new \Schoolbox\User\Search\Renderer($page, $result, $count, 0);
$renderer->render();

// render files
if (!empty($result['file'])) {
    $page->setFile('file_t', 'resource/file/search/results.html');
    $page->setBlock('file_t', 'file', 'file_block');
    $page->setBlock('file', 'modify', 'modify_block');
    $page->setVar(array(
            'FILE_DISP_COUNT' => count($result['file']),
            'FILE_COUNT' => $count['file'],
        ));

    $userHandler = new User();

    foreach ($result['file'] as $file) {
        foreach ($categoryStd->breadcrumb($file['category']) as $i => $breadcrumb) {
            $file['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";
        }

        $createdTime = new Alaress_Date_Relative($file['submitted']);

        $page->setVar(array(
                'FILE_ID' => $file['id'],
                'FILE_ICON' => $file['iconFile'],
                'FILE_SIZE' => Alaress_Byte_Format::bytesToString($file['file_size']),
                'FILE_DESCRIPTION' => Scrub::text($file['description']),
                'FILE_COMMENT' => TextFormatter::cutSentence(Scrub::text($file['comment']), 150),
                'FILE_OWNER' => $file['uid'],
                'FILE_OWNER_NAME' => $userHandler->formatName($file),
                'FILE_CREATED' => $createdTime->getRelativeTime(),
                'FILE_BREADCRUMB' => $file['breadcrumb'],
                'FILE_COUNT' => $count['file'],
            ));

        if ($file['perms_type'] < 2 && $file['userAccess_type'] < 2)
            $page->setVar('modify_block');
        else
            $page->parse('modify_block', 'modify');

        $page->parse('file_block', 'file', true);
    }

    outputSeeMore($page, $type, $count, 'file');

    $page->parse('RESULT', 'file_t', true);
}

// render categories
if (!empty($result['folder'])) {
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


$renderer = new \Schoolbox\Blog\Search\Renderer($page, $result, $count, 0);
$renderer->render();

$renderer = new \Schoolbox\Wiki\Search\Renderer($page, $result, $count, 0);
$renderer->render();

$renderer = new \Schoolbox\Forum\Search\Renderer($page, $result, $count, 0);
$renderer->render();

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

/* Omaha Beach
 *
 * Pagination Composite
 */
if (in_array($type, $omaha)) {
    $mapper->renderPagination($current_page);
}
/* Pagination
 * If we have a search type and have results to display
 */
else if ($result[$type] || $type == 'all') {

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
}

exit();