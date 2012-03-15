<?php
/**
 * Report_Boarder
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Report_Boarder extends Report_Parent {

	/**
	 * Constructor
	 *
	 * @param $page
	 */
	public function __construct($page) {
        $this->setPermissions();
		$this->setGroupHandler(new Group());

		if (!$this->checkPermissions()) {
			error(19);
		}
		else {
			$this->setPage($page);

			$this->setExternalUserHandler(new External_User());
			$this->getExternalUserHandler()->getCurrentSemester();

			$this->setup();

			$this->setAllRecords($this->getExternalUserHandler()->getStudentBoarderContactListExtended());
		}
	}

	/**
	 * Set up blocks and stuff
	 */
	protected function setup() {
		$this->getPage()->setFile(TEMPLATE_content, 'modules/report/boarders.html');
		$this->getPage()->setBlock(TEMPLATE_content, 'student', 'student_block');
		$this->getPage()->setTitle('Parent Contact info');
		$this->getPage()->setBlock('student', 'contact', 'contact_block');
		$this->getPage()->setBlock('contact', 'spouse', 'spouse_block');
		$this->getPage()->setBlock('contact', 'primary', 'primary_block');

		$this->getPage()->setVar(array(
			'student_block' => '',
			'contact_block' => '',
		));
	}
}



