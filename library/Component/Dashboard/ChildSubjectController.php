<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_ChildSubjectController extends Component_ControllerAbstract {

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

    public function render ($templateHandle) {

        $this->setBlock($templateHandle, "student_block", "student_subjects");
        $this->setBlock("student_block", "subject_block", "subject_item");

        $i = 0;

        foreach ( $this->children as $child ) {
            if ( !( Cfg::get('site_location') == 'wenona' && $child['gid'] == 3 ) ) {
                $i++;
                $query = "SELECT title, firstname, lastname, year FROM user WHERE id = {$child['id']}";
                $user = $this->db->getRow($query);
                $this->setVar("STUDENT_ID", $child['id']);
                $this->setVar("STUDENT_NAME", $this->user->formatName($user, '{firstname} {lastname}'));
                $this->setVar("STUDENT_YEAR", $user['year'] == 0 ? 'K' : $user['year']);

                if ( is_array($child['subjects']) ) {
                    foreach ( $child['subjects'] as $subject ) {
                        $this->setVar("STUDENT_SUBJECT_NAME", Scrub::text($subject['name']));
                        $this->setVar("STUDENT_SUBJECT_CODE", urlencode(urlencode($subject['code'])));
                        $this->parse("subject_item", "subject_block", true);
                    }
                }

                $this->parse("student_subjects", "student_block", true);
                $this->setVar('subject_item', '');
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