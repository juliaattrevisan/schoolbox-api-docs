<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_ChildSubjectController extends Component_Dashboard_ControllerAbstract {

    private $children;

    public function execute () {
        if ( is_array($_SESSION['children']) ) {
            $this->children = $_SESSION['children'];

            return true;
        }
        else {
            return false;
        }
    }

    public function renderTemplate ($templateHandle) {
        $userMapper = new \Schoolbox\UserMapper();

        if ( !$this->loadTemplate($templateHandle, 'childSubject') ) {
            return false;
        }

        $this->setBlock($templateHandle, "student_block", "student_subjects");
        $this->setBlock("student_block", "subject_block", "subject_item");
		$this->setBlock('student_block', 'eportfolio', 'eportfolio_block');
		$this->setBlock('student_block', 'due_work', 'due_work_block');
        $this->setBlock('student_block', 'grades_button', 'grades_button_block');
        $this->setBlock('student_block', 'timetable', 'timetable_block');

        $groupHandler = new Group();
		$isParent = $groupHandler->isParent();

        $i = 0;

        foreach ( $this->children as $child ) {

            // Check if the current childs timetable is viewable and hide if necessary

            $userChild = $userMapper->findById($child['id']);

            if ($userChild->getHideTimetable()) {
                $this->setVar('timetable_block', '');
            } else {
                $this->parse('timetable_block', 'timetable');
            }

            if ( !( Cfg::get('site_location') == 'wenona' && $child['gid'] == 3 ) ) {
                $i++;
                $query = "SELECT title, firstname, lastname, year, synergy_id FROM user WHERE id = {$child['id']}";
                $user = DeprecatedDatabase::getInstance()->getRow($query);
                $this->setVar("STUDENT_ID", $child['id']);
                $this->setVar("STUDENT_EXTERNAL_ID", $user['synergy_id']);
                $this->setVar("STUDENT_NAME", $child['name']);
                $this->setVar("STUDENT_YEAR", $user['year']);

                if ( is_array($child['subjects']) ) {
                    foreach ( $child['subjects'] as $subject ) {
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
				if ($isParent && Config::get('restrict_parent_duework_access')) {
					$this->setVar('due_work_block', '');
				}
				else {
					$this->parse('due_work_block', 'due_work');
				}

                if($isParent && Config::get('restrict_parent_grades') ) {
                    $this->setVar('grades_button_block', '');
                }
                else {
                    $this->parse('grades_button_block', 'grades_button');
                }



				/* ePortfolio link
				 */
				$authenticator = new \Eportfolio_Authenticator($_SESSION['id'], $child['id'], 'profile');

				if ($authenticator->canView()) {
					$this->parse('eportfolio_block', 'eportfolio');
				}
				else {
					$this->setVar('eportfolio_block', '');
				}

                $this->parse("student_subjects", "student_block", true);
                $this->setVar('subject_item', '');

				/* Clear children after parsing out the parent
				 */
				$this->setVar('due_work_block', '');
            }
        }


        if ( $i > 0 ) {
            return true;
        }
        else {
            return false;
        }

    }

}
