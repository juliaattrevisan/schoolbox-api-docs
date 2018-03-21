<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2017 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 *
 */

class Component_Dashboard_ChildSubjectController extends Component_Dashboard_ControllerAbstract
{

    private $children;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        if (is_array($_SESSION['children'])) {
            $this->children = $_SESSION['children'];
            return true;
        } else {
            return false;
        }
    }

    public function renderTemplate($templateHandle)
    {
        $userMapper = new \Schoolbox\UserMapper();

        if (!$this->loadTemplate($templateHandle, 'childSubject')) {
            return false;
        }

        $this->setBlock($templateHandle, "student_block", "student_subjects");
        $this->setBlock("student_block", "subject_block", "subject_item");
        $this->setBlock('student_block', 'reports', 'reports_block');
        $this->setBlock('student_block', 'eportfolio', 'eportfolio_block');
        $this->setBlock('student_block', 'pastoral', 'pastoral_block');
        $this->setBlock('student_block', 'due_work', 'due_work_block');
        $this->setBlock('student_block', 'grades_button', 'grades_button_block');
        $this->setBlock('student_block', 'timetable', 'timetable_block');

        $isParent = $this->getUser()->getRole()->isParent();

        $i = 0;

        foreach ($this->children as $child) {

            // Check if the current childs timetable is viewable and hide if necessary
            try {
                $userChild = $userMapper->findById($child['id']);
                $userChild->loadParents();
            } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
                continue;
            }


            if (!($this->getConfigMapper()->get('site_location') == 'wenona' && $child['gid'] == 3)) {
                $i++;

                $this->setVar("STUDENT_ID", $userChild->getId());
                $this->setVar("STUDENT_EXTERNAL_ID", $userChild->getExternalId());
                $this->setVar("STUDENT_NAME", $child['name']);
                $this->setVar("STUDENT_YEAR", $userChild->getYear());


                if ($this->getConfigMapper()->get('pastoral_enabled') &&
                    $this->getUser()->isParentOf($userChild) &&
                    $this->getConfigMapper()->get('pastoral_parent_access')) {
                    $this->parse('pastoral_block', 'pastoral');
                } else {
                    $this->setVar('pastoral_block', '');
                }

                if (is_array($child['subjects'])) {
                    foreach ($child['subjects'] as $subject) {
                        $this->setVar("STUDENT_SUBJECT_NAME", Scrub::text($subject['name']));
                        $this->setVar("STUDENT_SUBJECT_CODE", urlencode(urlencode($subject['code'])));
                        $this->parse("subject_item", "subject_block", true);
                    }
                }

                /* Output calendar date to show */
                $now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');
                $this->setVar('CAL_TODAY_DATE', $now->getURLDate());

                /* Hide Due Work link from parents, if Due Work access is restricted
                 */
                if ($isParent && $this->getConfigMapper()->get('restrict_parent_duework_access')) {
                    $this->setVar('due_work_block', '');
                } else {
                    $this->parse('due_work_block', 'due_work');
                }

                if ($isParent && $this->getConfigMapper()->get('restrict_parent_grades')) {
                    $this->setVar('grades_button_block', '');
                } else {
                    $this->parse('grades_button_block', 'grades_button');
                }

                if ($userChild->getHideTimetable()) {
                    $this->setVar('timetable_block', '');
                } else {
                    $this->parse('timetable_block', 'timetable');
                }

                /* ePortfolio link
                 */
                $authenticator = new \Eportfolio_Authenticator($_SESSION['id'], $child['id'], 'profile');

                if ($authenticator->canView()) {
                    $this->parse('eportfolio_block', 'eportfolio');
                } else {
                    $this->setVar('eportfolio_block', '');
                }

                /* Academic report link
                */
                if ($this->getConfigMapper()->get('external_document_report_enabled')) {
                    $this->parse('reports_block', 'reports');
                } else {
                    $this->setVar('reports_block');
                }

                $this->parse("student_subjects", "student_block", true);
                $this->setVar('subject_item', '');

                /* Clear children after parsing out the parent
                 */
                $this->setVar('due_work_block', '');
            }
        }


        if ($i > 0) {
            return true;
        } else {
            return false;
        }

    }

}
