<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\User\Search;

use Schoolbox\Resource\Search\Renderer\Handler;
use Scrub;
use Alaress_Date_Format;
use Config;
use Cfg;
use Eportfolio_Authenticator;

class Renderer extends Handler {

    /**
     * Type (e.g. 'link', 'blog', etc)
     *
     * @var string
     */
    protected $type = 'user';

    /**
     * Tab Name (e.g. 'Link', 'Blogs', etc);
     *
     * @var string
     */
    protected $tabName = 'People';

    /**
     * Render
     */
    public function render() {
        $userHandler = new \User();
        $groupHandler = new \Group();

        $result = $this->getResult($this->getType());

        if (!empty($result)) {
            $this->getPage()->setFile('user_t', 'user/search/results.html');
            $this->getPage()->setBlock('user_t', 'user', 'user_block');
            $this->getPage()->setBlock('user', 'due_work', 'due_work_block');
            $this->getPage()->setBlock('user', 'grades_button', 'grades_button_block');
            $this->getPage()->setBlock('user', 'timetable', 'timetable_block');
            $this->getPage()->setBlock('user', 'eportfolio', 'eportfolio_block');
            $this->getPage()->setBlock('user', 'modify', 'modify_block');
            $this->getPage()->setBlock('user', 'boarder_leave', 'boarder_leave_block');
            $this->getPage()->setBlock('user', 'user_calendar', 'user_calendar_block');

            $this->getPage()->setVar(array(
                'USER_DISP_COUNT' => count($this->getResult($this->getType())),
                'USER_COUNT' => $this->getCount($this->getType()),
            ));

            foreach ($result as $user) {
                $this->getPage()->setVar(array(
                    'USER_SEARCH_ID' => $user['id'],
                    'USER_SEARCH_SYNERGY_ID' => $user['synergy_id'],
                    'USER_SEARCH_NAME' => Scrub::text($userHandler->formatName($user)),
                    'USER_SEARCH_COUNT' => $this->getCount($this->getType()),
                    'USER_SEARCH_YEAR' => empty($user['year']) ? '' : "Year {$user['year']}",
                    'USER_SEARCH_CAMPUS' => Scrub::text(
                            stristr($user['group_name'], $user['campus_name']) ? '' : $user['campus_name']
                        ),
                    'USER_SEARCH_GROUP' => Scrub::text($user['group_name']),
                ));

                $this->getPage()->setVar('timetable_block');
                $this->getPage()->setVar('eportfolio_block');
                $this->getPage()->setVar('boarder_leave_block');

                /* Hide Due Work link from parents, if Due Work access is restricted
                 */
                if ($groupHandler->isParent() && Config::get('restrict_parent_duework_access')) {
                    $this->getPage()->setVar('due_work_block', '');
                } elseif (!$groupHandler->isStudent($user['id']) || (!$groupHandler->isStaff() && !$groupHandler->isParentOfChild($user['id']))) {
                    $this->getPage()->setVar('due_work_block');
                } else {
                    $this->getPage()->parse('due_work_block', 'due_work');
                }

                if($groupHandler->isParent() && Config::get('restrict_parent_grades') ) {
                    $this->getPage()->setVar('grades_button_block', '');
                } elseif (!$groupHandler->isStudent($user['id']) || (!$groupHandler->isStaff() && !$groupHandler->isParentOfChild($user['id']))) {
                    $this->getPage()->setVar('grades_button_block', '');
                } else {
                    $this->getPage()->parse('grades_button_block', 'grades_button');
                }

                //Show student timetables to staff and parents of child
                if ($groupHandler->isStudent($user['id'])) {
                    if ($groupHandler->isStaff() || $groupHandler->isParentOfChild($user['id'])) {

                        /* Timetable shouldn't appear if synergy is disabled in the conf
                         */
                        if (Cfg::get('external_enable') && $user['synergy_id']) {
                            $this->getPage()->parse('timetable_block', 'timetable');
                        } else {
                            $this->getPage()->setVar('timetable_block', '');
                        }

                    }
                    if (Cfg::get('boarder_leave_enabled') == '1') {
                        if ($groupHandler->isStaff() and $user['residential_house_id']) {
                            $this->getPage()->parse('boarder_leave_block', 'boarder_leave');
                        }
                    }
                } else {
                    //Show staff timetables to other staff
                    if ($groupHandler->isStaff() && $groupHandler->isStaff($user['id']))
                        $this->getPage()->parse('timetable_block', 'timetable');
                }

                if (!$_SESSION['su'])
                    $this->getPage()->setVar('modify_block');
                else
                    $this->getPage()->parse('modify_block', 'modify');

                /* ePortfolio link
                 */
                $authenticator = new Eportfolio_Authenticator($_SESSION['id'], $user['id'], 'profile');

                if ($authenticator->canView()) {
                    $this->getPage()->parse('eportfolio_block', 'eportfolio');
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

                    $this->getPage()->setVar(array(
                            'USER_CALENDAR_CURRENTDATE' => $now->getUsingFormat('d-m-Y'),
                        ));

                    $this->getPage()->parse('user_calendar_block', 'user_calendar');
                } else {
                    $this->getPage()->setVar('user_calendar_block', '');
                }

                $this->getPage()->parse('user_block', 'user', true);
            }

            $this->outputSeeMore();

            $this->getPage()->parse('RESULT', 'user_t', true);
        }
    }
}

