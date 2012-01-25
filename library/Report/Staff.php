<?php
/**
 * Report_Staff
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Report_Staff extends Report_Abstract {

	/**
	 * Constructor
	 *
	 * @param $page
	 */
	public function __construct($page) {
		$this->setGroupHandler(new Group());

		if (!$this->checkPermissions()) {
			error(19);
		}
		else {
			$this->setPage($page);

			$this->setExternalUserHandler(new External_User());
			$this->getExternalUserHandler()->getCurrentSemester();

			$this->setup();
		}
	}


	/**
	 * Set up blocks and stuff
	 */
	protected function setup() {
		$this->getPage()->setFile(TEMPLATE_content, 'modules/report/staff.html');
		$this->getPage()->setBlock(TEMPLATE_content, 'staff', 'staff_block');
		$this->getPage()->setTitle('Staff Contact info');

		$this->getPage()->setVar(array(
			'staff_block' => '',
		));
	}

	/**
	 * Render data
	 */
	public function render() {
		$allStaff = $this->getExternalUserHandler()->getStaffContactListExtended();

		if (is_array($allStaff) && count($allStaff)) {
			foreach($allStaff as $currentStaff) {


				$this->getPage()->setVar(array(
					'STAFF_STAFFID' => $currentStaff['StaffID'],
					'STAFF_TITLE' => $currentStaff['StaffTitle'],
					'STAFF_FIRSTNAME' => $currentStaff['StaffPreferredName'],
					'STAFF_SURNAME' => $currentStaff['StaffSurname'],
					'STAFF_ROLE' => $currentStaff['StaffCategoryType'],
					'STAFF_DEPARTMENT' => $currentStaff['StaffDepartment'],
					'STAFF_CAMPUS' => $currentStaff['StaffCampusDescription'],
					'STAFF_PHONE' => $currentStaff['StaffPhone'],
					'STAFF_EMAIL' => $currentStaff['StaffOccupEmail'],
				));

				$this->getPage()->parse('staff_block', 'staff', true);
			}
		}
	}
}



