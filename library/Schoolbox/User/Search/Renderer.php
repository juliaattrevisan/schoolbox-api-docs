<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\User\Search;

use Schoolbox\Resource\Search\Renderer\Handler;
use Scrub;
use Alaress_Date_Format;
use Config;
use Eportfolio_Authenticator;

class Renderer extends Handler
{

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
    public function render()
    {
        $userHandler = new \User();
        $groupHandler = new \Group();

        $result = $this->getResult($this->getType());

        if (!empty($result)) {
            $this->getPage()->setFile('user_t', 'user/search/results.html');
            $this->getPage()->setBlock('user_t', 'user', 'user_block');
            $this->getPage()->setBlock('user', 'due_work', 'due_work_block');
            $this->getPage()->setBlock('user', 'grades_button', 'grades_button_block');
            $this->getPage()->setBlock('user', 'timetable', 'timetable_block');
            $this->getPage()->setBlock('user', 'pastoral', 'pastoral_block');
            $this->getPage()->setBlock('user', 'eportfolio', 'eportfolio_block');
            $this->getPage()->setBlock('user', 'modify', 'modify_block');
            $this->getPage()->setBlock('user', 'tot', 'tot_block');
            $this->getPage()->setBlock('modify', 'modify_enabled', 'modify_enabled_block');
            $this->getPage()->setBlock('modify', 'modify_disabled', 'modify_disabled_block');
            $this->getPage()->setBlock('user', 'position_title', 'position_title_block');
            $this->getPage()->setBlock('user', 'normal_title', 'normal_title_block');
            $this->getPage()->setBlock('user', 'boarder_leave', 'boarder_leave_block');
            $this->getPage()->setBlock('user', 'academic_reports', 'academic_reports_block');
            $this->getPage()->setBlock('user', 'user_calendar', 'user_calendar_block');

            $this->getPage()->setVar(array(
                'USER_DISP_COUNT' => count($this->getResult($this->getType())),
                'USER_COUNT'      => $this->getCount($this->getType()),
                'CURRENT_TAB'     => $this->getCurrentTab(),
            ));

            foreach ($result as $user) {

                /* Remember if any actions were available to render in a tot
                * If the`re were no actions, hide the tot
                * because phplib, have to do it here
                */
                $hasActions = false;

                $this->getPage()->setVar(array(
                    'USER_SEARCH_ID' => $user['id'],
                    'USER_SEARCH_SYNERGY_ID' => $user['synergy_id'],
                    'USER_SEARCH_NAME' => Scrub::text($userHandler->formatName($user)),
                    'USER_SEARCH_USERNAME' => Scrub::text($user['username']),
                    'USER_SEARCH_COUNT' => $this->getCount($this->getType()),
                    'USER_SEARCH_YEAR' => empty($user['year']) ? '' : "Year {$user['year']}",
                    'USER_POSITION_TITLE' => $user['position_title'],
                    'USER_SEARCH_CAMPUS' => Scrub::text(
                        stristr($user['group_name'], $user['campus_name']) ? '' : $user['campus_name']
                    ),
                    'USER_SEARCH_GROUP' => Scrub::text($user['group_name'])
                ));

                $this->getPage()->setVar('timetable_block');
                $this->getPage()->setVar('pastoral_block');
                $this->getPage()->setVar('eportfolio_block');
                $this->getPage()->setVar('boarder_leave_block');
                $this->getPage()->setVar('academic_reports_block');
                $this->getPage()->setVar('tot_block');

                /* Hide Due Work link from parents, if Due Work access is restricted
                 */
                if ($groupHandler->isParent() && Config::get('restrict_parent_duework_access')) {
                    $this->getPage()->setVar('due_work_block', '');
                } elseif (!$groupHandler->isStudent($user['id']) || (!$groupHandler->isStaff() && !$groupHandler->isParentOfChild($user['id']))) {
                    $this->getPage()->setVar('due_work_block', '');
                } else {
                    $this->getPage()->parse('due_work_block', 'due_work');
                    $hasActions = true;
                }

                if (Config::get('external_document_report_enabled')
                    && $groupHandler->isStudent($user['id'])
                    && ($groupHandler->isStaff() || $groupHandler->isParentOfChild($user['id']))
                ) {
                    $this->getPage()->parse('academic_reports_block', 'academic_reports');
                    $hasActions = true;
                }

                if ($groupHandler->isParent() && Config::get('restrict_parent_grades')) {
                    $this->getPage()->setVar('grades_button_block', '');
                } elseif (!$groupHandler->isStudent($user['id']) || (!$groupHandler->isStaff() && !$groupHandler->isParentOfChild($user['id']))) {
                    $this->getPage()->setVar('grades_button_block', '');
                } else {
                    $this->getPage()->parse('grades_button_block', 'grades_button');
                    $hasActions = true;
                }

                // Hide if disabled or not a student
                if (\Config::get('pastoral_enabled') != 1 || !$groupHandler->isStudent($user['id'])) {
                    $this->getPage()->setVar('pastoral_block', '');
                }
                else
                {
                    // Show this student's pastoral link to staff, or parents if config allows.
                    if ( $_SESSION['user']->getRole()->isStaff() ||
                        ($groupHandler->isParentOfChild($user['id']) && \Config::get('pastoral_parent_access'))
                    ){
                        $this->getPage()->parse('pastoral_block', 'pastoral');
                        $hasActions = true;
                    } else {
                        $this->getPage()->setVar('pastoral_block', '');
                    }
                }

                //Show student timetables to staff and parents of child
                if (empty($user['position_title'])) {
                    $this->getPage()->parse('normal_title_block', 'normal_title');
                    $this->getPage()->setVar('position_title_block', '');
                } else {
                    $this->getPage()->parse('position_title_block', 'position_title');
                    $this->getPage()->setVar('normal_title_block', '');
                }

                //Show student timetables to staff and parents of child
                if ($groupHandler->isStudent($user['id'])) {
                    if ($_SESSION['user']->getRole()->isStaff() || $groupHandler->isParentOfChild($user['id'])) {
                        // Timetable shouldn't appear if synergy is disabled in the conf, or the students timetable is marked as hidden
                        if (Config::get('external_enable') && $user['synergy_id'] && !$user['hide_timetable']) {
                            $this->getPage()->parse('timetable_block', 'timetable');
                            $hasActions = true;
                        } else {
                            $this->getPage()->setVar('timetable_block', '');
                        }

                    }
                    if (Config::get('boarder_leave_enabled') == '1') {
                        if ($groupHandler->isStaff() and $user['residential_house_id']) {
                            $this->getPage()->parse('boarder_leave_block', 'boarder_leave');
                            $hasActions = true;
                        }
                    }
                } else {
                    //Show staff timetables to other staff
                    if ($_SESSION['user']->getRole()->isStaff() && $groupHandler->isStaff($user['id'])) {
                        $this->getPage()->parse('timetable_block', 'timetable');
                        $hasActions = true;
                    }
                }
                if (!$_SESSION['user']->isSuperuser()) {
                    $this->getPage()->setVar('modify_block');
                } else {
                    if ($user['enabled']) {
                        $this->getPage()->parse('modify_enabled_block', 'modify_enabled');
                        $this->getPage()->setVar('modify_disabled_block');
                    } else {
                        $this->getPage()->parse('modify_disabled_block', 'modify_disabled');
                        $this->getPage()->setVar('modify_enabled_block');
                    }

                    $this->getPage()->parse('modify_block', 'modify');
                }

                /* ePortfolio link
                 */
                $authenticator = new Eportfolio_Authenticator($_SESSION['id'], $user['id'], 'profile');

                if ($authenticator->canView()) {
                    $this->getPage()->parse('eportfolio_block', 'eportfolio');
                    $hasActions = true;
                }

                /* Calendar link
                 * Calendar only exist for students' parents, staff, supers, and themselves
                 */
                $isParentOfChild = $groupHandler->isParentOfChild($user['id']);
                $isSuperuser = $_SESSION['user']->isSuperuser();
                $isStaff =  $_SESSION['user']->getRole()->isStaff();
                $isSelf = ($user['id'] == $_SESSION['user']->getId());

                if ($isParentOfChild || $isSuperuser || $isStaff || $isSelf) {
                    $now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

                    $this->getPage()->setVar(array(
                            'USER_CALENDAR_CURRENTDATE' => $now->getURLDate(),
                        ));

                    $this->getPage()->parse('user_calendar_block', 'user_calendar');
                    $hasActions = true;
                } else {
                    $this->getPage()->setVar('user_calendar_block', '');
                }

                /* If the tot had any actions in it, show it
                 * Otherwise, hide it (which it does initially anyway)
                 */
                if ($hasActions) {
                    $this->getPage()->parse('tot_block', 'tot');
                }

                $this->getPage()->parse('user_block', 'user', true);
            }

            $this->outputSeeMore();

            $this->getPage()->parse('RESULT', 'user_t', true);
        } else {
            $this->getPage()->setVar('see_more_block', '');

            return;
        }
    }
}
