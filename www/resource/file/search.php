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
$fileHandler = new File();
$type = 'file';

/* search() calls access a global counter array, $count
 */
$result[$type] = $fileHandler->search($keyword, 0, Cfg::get('search_all_limit'), $count[$type]);

$count['all'] = array_sum($count);

$result[$type] = $fileHandler->search($keyword, ($current_page - 1) * Cfg::get('search_type_limit'), Cfg::get('search_type_limit'), $count[$type]);

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