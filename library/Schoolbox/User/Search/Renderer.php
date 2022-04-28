<?php

/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\User\Search;

use Alaress_Date_Format;
use Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\Search\Renderer\Handler;
use Schoolbox\User;
use Scrub;

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
     * TODO: Remove UserHandler usage in favour of UserMapper
     *
     * This is cloned from library/Schoolbox/User.php
     * This entire search processor is using User Handler, returning array data
     * which precludes usage of the model's getLongName() implementation
     *
     * If the year's name contains only an integer, presume the user wanted
     * to prepend "Year " in front of it, because this is how it will
     * manifest in a UI somewhere.
     *
     * @param $yearName
     * @return string
     */
    private function getLongName($yearName): string
    {
        if (preg_match('/^[0-9]+$/', $yearName)) {
            return "Year {$yearName}";
        }

        return $yearName;
    }


    /**
     * Render
     */
    public function render()
    {
        $userHandler  = new \User();
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
            $this->getPage()->setBlock('user', 'user_name_link', 'user_name_link_block');
            $this->getPage()->setBlock('user', 'user_name_no_link', 'user_name_no_link_block');
            $this->getPage()->setBlock('user', 'position_title', 'position_title_block');
            $this->getPage()->setBlock('user', 'normal_title', 'normal_title_block');
            $this->getPage()->setBlock('user', 'boarder_leave', 'boarder_leave_block');
            $this->getPage()->setBlock('user', 'academic_reports', 'academic_reports_block');
            $this->getPage()->setBlock('user', 'user_calendar', 'user_calendar_block');

            $this->getPage()->setVar([
                'USER_DISP_COUNT' => count($this->getResult($this->getType())),
                'USER_COUNT'      => $this->getCount($this->getType()),
                'CURRENT_TAB'     => $this->getCurrentTab(),
            ]);

            /** @var User $currentUser */
            $currentUser            = $_SESSION['user'];
            $currentUserIsSuperuser = $currentUser->isSuperuser();
            $currentUserIsStaff     = $currentUser->getRole()->isStaff();
            $currentUserIsStudent   = $currentUser->getRole()->isStudent();
            $currentUserIsParent    = $currentUser->getRole()->isParent();

            foreach ($result as $user) {
                /* Remember if any actions were available to render in a tot
                * If the`re were no actions, hide the tot
                * because phplib, have to do it here
                */
                $hasActions = false;

                $this->getPage()->setVar([
                    'USER_SEARCH_ID'         => $user['id'],
                    'USER_SEARCH_SYNERGY_ID' => $user['synergy_id'],
                    'USER_SEARCH_NAME'       => Scrub::text($userHandler->formatName($user)),
                    'USER_SEARCH_USERNAME'   => Scrub::text($user['username']),
                    'USER_SEARCH_COUNT'      => $this->getCount($this->getType()),
                    'USER_SEARCH_YEAR'       => $this->getLongName((string)$user['year_name']),
                    'USER_POSITION_TITLE'    => $user['position_title'],
                    // For now, do not output a user's campus
                    'USER_SEARCH_CAMPUS'     => '',
                    'USER_SEARCH_GROUP'      => Scrub::text($user['group_name']),
                ]);

                $this->getPage()->setVar('timetable_block');
                $this->getPage()->setVar('pastoral_block');
                $this->getPage()->setVar('eportfolio_block');
                $this->getPage()->setVar('boarder_leave_block');
                $this->getPage()->setVar('academic_reports_block');
                $this->getPage()->setVar('tot_block');

                $resultIsStaff               = $groupHandler->isStaff($user['id']);
                $resultIsStudent             = $groupHandler->isStudent($user['id']);
                $currentUserIsParentOfResult = $groupHandler->isParentOfChild($user['id']);
                $currentUserIsResult         = ($user['id'] == $currentUser->getId());

                /* Hide Due Work link from parents, if Due Work access is restricted or the module is disabled
                 */
                $this->getPage()->setVar('due_work_block', '');
                if (Config::get(ConfigMapper::NAME_UPCOMING_ASSESSMENTS_ENABLED)) {
                    if (
                        ($currentUserIsStaff && $currentUser->getRole()->getViewStudentGrades())
                        || ($currentUserIsStudent && $currentUserIsResult)
                        || ($currentUserIsParent
                            && $currentUserIsParentOfResult
                            && !Config::get('restrict_parent_duework_access')
                        )
                    ) {
                        $this->getPage()->parse('due_work_block', 'due_work');
                        $hasActions = true;
                    }
                }

                if (
                    $resultIsStudent
                    && ($currentUserIsStaff || $currentUserIsParentOfResult)
                    && Config::get('external_document_report_enabled')
                ) {
                    $this->getPage()->parse('academic_reports_block', 'academic_reports');
                    $hasActions = true;
                }

                // Hide grades from parents, if grades access is restricted or module is disabled
                $this->getPage()->setVar('grades_button_block', '');
                if (Config::get(ConfigMapper::NAME_GRADES_ENABLED)) {
                    if (
                        ($currentUserIsStaff && $currentUser->getRole()->getViewStudentGrades())
                        || ($currentUserIsStudent && $currentUserIsResult)
                        || ($currentUserIsParent
                            && $currentUserIsParentOfResult
                            && !Config::get('restrict_parent_grades')
                        )
                    ) {
                        $this->getPage()->parse('grades_button_block', 'grades_button');
                        $hasActions = true;
                    }
                }

                // Show this student's pastoral link to staff, or parents if config allows.
                $this->getPage()->setVar('pastoral_block', '');
                if (
                    $resultIsStudent
                    && Config::get('pastoral_enabled')
                    && (
                        ($currentUser->isStaffWithPastoralAccess())
                        || ($currentUserIsParentOfResult && Config::get(ConfigMapper::NAME_PASTORAL_PARENT_ACCESS_ENABLED))
                    )
                ) {
                    $this->getPage()->parse('pastoral_block', 'pastoral');
                    $hasActions = true;
                }

                //Show student timetables to staff and parents of child
                if (empty($user['position_title'])) {
                    $this->getPage()->parse('normal_title_block', 'normal_title');
                    $this->getPage()->setVar('position_title_block', '');
                } else {
                    $this->getPage()->parse('position_title_block', 'position_title');
                    $this->getPage()->setVar('normal_title_block', '');
                }

                if ($groupHandler->isGuest($user['id'])) {
                    $this->getPage()->parse('user_name_no_link_block', 'user_name_no_link');
                    $this->getPage()->setVar('user_name_link_block', '');
                } else {
                    $this->getPage()->parse('user_name_link_block', 'user_name_link');
                    $this->getPage()->setVar('user_name_no_link_block', '');
                }

                // Show student timetables to staff and parents of child
                $this->getPage()->setVar('timetable_block', '');
                if ($resultIsStudent) {
                    if ($currentUserIsStaff || $currentUserIsParentOfResult) {
                        // Timetable shouldn't appear if synergy is disabled in the conf,
                        // or the students timetable is marked as hidden
                        if ($user['synergy_id'] && !$user['hide_timetable'] && Config::get('external_enable')) {
                            $this->getPage()->parse('timetable_block', 'timetable');
                            $hasActions = true;
                        }
                    }
                } elseif ($currentUserIsStaff && $resultIsStaff) {
                    // Show staff timetables to other staff
                    $this->getPage()->parse('timetable_block', 'timetable');
                    $hasActions = true;
                }

                // Boarder Leave: only show to res house students if enabled
                if (
                    $resultIsStudent
                    && $currentUserIsStaff
                    && $user['residential_house_id']
                    && Config::get('boarder_leave_enabled')
                ) {
                    $this->getPage()->parse('boarder_leave_block', 'boarder_leave');
                    $hasActions = true;
                }

                // Modify: show only to supers
                if (!$currentUserIsSuperuser) {
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
                if (
                    Config::get(ConfigMapper::NAME_EPORTFOLIO_ENABLED)
                    && $user['eportfolio_enabled']
                    && ($currentUserIsSuperuser
                        || ($currentUserIsStaff && $currentUser->getRole()->getAccessEportfolio())
                        || $currentUserIsResult
                        || $currentUserIsParentOfResult)
                ) {
                    $this->getPage()->parse('eportfolio_block', 'eportfolio');
                    $hasActions = true;
                }

                /* Calendar link
                 * Calendar only exist for students' parents, staff, supers, and themselves
                 */
                if (
                    $currentUserIsParentOfResult
                    || $currentUserIsSuperuser
                    || ($currentUserIsStaff && (!$resultIsStudent || $currentUser->getRole()->getViewStudentCalendar()))
                    || $currentUserIsResult
                ) {
                    $now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

                    $this->getPage()->setVar([
                        'USER_CALENDAR_CURRENTDATE' => $now->getURLDate(),
                    ]);

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
