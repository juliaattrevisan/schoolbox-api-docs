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

$renderer = new \Schoolbox\File\Search\Renderer($page, $result, $count, 0);
$renderer->render();

$renderer = new \Schoolbox\Folder\Search\Renderer($page, $result, $count, 0);
$renderer->render();

$renderer = new \Schoolbox\Folder\News\Search\Renderer($page, $result, $count, 0);
$renderer->render();

$renderer = new \Schoolbox\News\Search\Renderer($page, $result, $count, 0);
$renderer->render();

$renderer = new \Schoolbox\Blog\Search\Renderer($page, $result, $count, 0);
$renderer->render();

$renderer = new \Schoolbox\Wiki\Search\Renderer($page, $result, $count, 0);
$renderer->render();

$renderer = new \Schoolbox\Forum\Search\Renderer($page, $result, $count, 0);
$renderer->render();

$renderer = new \Schoolbox\Calendar\Event\Search\Renderer($page, $result, $count, 0);
$renderer->render();

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