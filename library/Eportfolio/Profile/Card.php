<?php
/**
 * Eportfolio_Profile_Card
 *
 * This shows the name, image, house and campus information for
 * the dude's eportfolio that you're currently viewing.
 *
 * Originally some kind of hybrid with privateNotes
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Eportfolio_Profile_Card extends Eportfolio_Profile_Abstract {

	/**
	 * Render component
	 *
	 * @return void
	 */
	public function render() {
		$this->getPage()->setBlock(TEMPLATE_content, 'student', 'student_block');

		$userHandler = new User();
        $groupHandler = new Group();

        $user = $userHandler->getByID($this->getUserId());

        if($groupHandler->isStudent() ) {

            $this->getPage()->setVar('CAMPUS', $user['campusName']);
            $this->getPage()->setVar('YEAR', $user['year']);

            if(Cfg::get('external_enable')) {
                $extUserHandler = new External_User();

                $student = $extUserHandler->getStudent($user['synergy_id']);
                $this->getPage()->setVar(array(
                                              'YEAR' => Scrub::text($student['StudentYearLevel']),
                                              'HOUSE' => Scrub::text($student['StudentHouseDescription'])));
                $this->getPage()->parse('student_block','student');
            }

        }
        else {
               $this->getPage()->setVar('sudent_block','');
            }
	
	}
}
