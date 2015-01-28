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
$userHandler = new User();
$type = 'user';

/* search() calls access a global counter array, $count
 */
$result[$type] = $userHandler->search($keyword, 0, Cfg::get('search_all_limit'), $count[$type]);

$count['all'] = array_sum($count);

$result[$type] = $userHandler->search($keyword, ($current_page - 1) * Cfg::get('search_type_limit'), Cfg::get('search_type_limit'), $count[$type]);

// render users
if (!empty($result['user'])) {
    $page->setFile('user_t', 'user/search/results.html');
    $page->setBlock('user_t', 'user', 'user_block');
    $page->setBlock('user', 'due_work', 'due_work_block');
    $page->setBlock('user', 'grades_button', 'grades_button_block');
    $page->setBlock('user', 'timetable', 'timetable_block');
    $page->setBlock('user', 'eportfolio', 'eportfolio_block');
    $page->setBlock('user', 'modify', 'modify_block');
    $page->setBlock('user', 'boarder_leave', 'boarder_leave_block');
    $page->setBlock('user', 'user_calendar', 'user_calendar_block');

    $groupHandler = new Group();
    $userHandler = new User();

    $page->setVar(array(
            'USER_DISP_COUNT' => count($result['user']),
            'USER_COUNT' => $count[$type],
        ));

    foreach ($result['user'] as $user) {

        $page->setVar(array(
                'USER_SEARCH_ID' => $user['id'],
                'USER_SEARCH_SYNERGY_ID' => $user['synergy_id'],
                'USER_SEARCH_NAME' => Scrub::text($userHandler->formatName($user)),
                'USER_SEARCH_COUNT' => $count[$type],
                'USER_SEARCH_YEAR' => empty($user['year']) ? '' : "Year {$user['year']}",
                'USER_SEARCH_CAMPUS' => Scrub::text(
                        stristr($user['group_name'], $user['campus_name']) ? '' : $user['campus_name']
                    ),
                'USER_SEARCH_GROUP' => Scrub::text($user['group_name']),
            ));

        $page->setVar('timetable_block');
        $page->setVar('eportfolio_block');
        $page->setVar('boarder_leave_block');

        /* Hide Due Work link from parents, if Due Work access is restricted
		 */
        if ($group->isParent() && Config::get('restrict_parent_duework_access')) {
            $page->setVar('due_work_block', '');
        } elseif (!$group->isStudent($user['id']) || (!$group->isStaff() && !$group->isParentOfChild($user['id']))) {
            $page->setVar('due_work_block');
        } else {
            $page->parse('due_work_block', 'due_work');
        }

        if($groupHandler->isParent() && Config::get('restrict_parent_grades') ) {
            $page->setVar('grades_button_block', '');
        } elseif (!$group->isStudent($user['id']) || (!$group->isStaff() && !$group->isParentOfChild($user['id']))) {
            $page->setVar('grades_button_block', '');
        } else {
            $page->parse('grades_button_block', 'grades_button');
        }



        //Show student timetables to staff and parents of child
        if ($group->isStudent($user['id'])) {
            if ($group->isStaff() || $group->isParentOfChild($user['id'])) {

                /* Timetable shouldn't appear if synergy is disabled in the conf
                 */
                if (Cfg::get('external_enable') && $user['synergy_id']) {
                    $page->parse('timetable_block', 'timetable');
                } else {
                    $page->setVar('timetable_block', '');
                }

            }
            if (Cfg::get('boarder_leave_enabled') == '1') {
                if ($group->isStaff() and $user['residential_house_id']) {
                    $page->parse('boarder_leave_block', 'boarder_leave');
                }
            }
        } else {
            //Show staff timetables to other staff
            if ($group->isStaff() && $group->isStaff($user['id']))
                $page->parse('timetable_block', 'timetable');
        }

        if (!$_SESSION['su'])
            $page->setVar('modify_block');
        else
            $page->parse('modify_block', 'modify');

        /* ePortfolio link
		 */
        $authenticator = new \Eportfolio_Authenticator($_SESSION['id'], $user['id'], 'profile');

        if ($authenticator->canView()) {
            $page->parse('eportfolio_block', 'eportfolio');
        }

        /* Calendar link
		 * Calendar only exist for students' parents, staff, supers, and themselves
		 */
        $isParentOfChild = $groupHandler->isParentOfChild($user['id']);
        $isSuperuser = $_SESSION['user']->getSuperuser();
        $isStaff = $groupHandler->isStaff();
        $isSelf = ($user['id'] == $_SESSION['user']->getId());

        if ($isParentOfChild || $isSuperuser || $isStaff || $isSelf) {
            $now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

            $page->setVar(array(
                    'USER_CALENDAR_CURRENTDATE' => $now->getUsingFormat('d-m-Y'),
                ));

            $page->parse('user_calendar_block', 'user_calendar');
        } else {
            $page->setVar('user_calendar_block', '');
        }

        $page->parse('user_block', 'user', true);
    }

    outputSeeMore($page, $type, $count, 'user');

    $page->parse('RESULT', 'user_t', true);
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