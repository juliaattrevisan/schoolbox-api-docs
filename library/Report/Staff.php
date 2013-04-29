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

			$this->setAllRecords($this->getExternalUserHandler()->getStaffContactListExtended());
		}
	}

	/**
	 * Check permissions. Only staff and superusers can view this page
	 *
	 * @return bool
	 */
	protected function checkPermissions() {
		if ($this->getGroupHandler()->isSuperuser() || $this->getGroupHandler()->isStaff() || $this->getGroupHandler()->isParent()) {
			return true;
		}

		return false;
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
		$allStaff = $this->getAllRecords();

		if (is_array($allStaff) && count($allStaff)) {
			$jobPositionDescription = array();
			$previousStaff = null;

			/* Agglutinate multiple JobPositionDescription
			 */
			foreach($allStaff as $currentStaff) {
				$jobPositionDescription[] = $previousStaff['JobPositionDescription'];

				if ($previousStaff['StaffID'] != $currentStaff['StaffID']) {
					if ($previousStaff != null) {
						$this->getPage()->setVar(array(
							'STAFF_STAFFID' => $previousStaff['StaffID'],
							'STAFF_TITLE' => $previousStaff['StaffTitle'],
							'STAFF_FIRSTNAME' => $previousStaff['StaffPreferredName'],
							'STAFF_SURNAME' => $previousStaff['StaffSurname'],
							'STAFF_ROLE' => implode(', ', $jobPositionDescription),
							'STAFF_DEPARTMENT' => $previousStaff['StaffDepartment'],
							'STAFF_CAMPUS' => $previousStaff['StaffCampusDescription'],
							'STAFF_EXTENSION' => $previousStaff['StaffExtension'],
							'STAFF_PHONE' => $previousStaff['StaffOccupPhone'],
							'STAFF_EMAIL' => $previousStaff['StaffOccupEmail'],
						));

						$this->getPage()->parse('staff_block', 'staff', true);
					}

					$jobPositionDescription = array();
				}

				$previousStaff = $currentStaff;
			}
//
			/* Last person
			 */
			$this->getPage()->setVar(array(
				'STAFF_STAFFID' => $currentStaff['StaffID'],
				'STAFF_TITLE' => $currentStaff['StaffTitle'],
				'STAFF_FIRSTNAME' => $currentStaff['StaffPreferredName'],
				'STAFF_SURNAME' => $currentStaff['StaffSurname'],
				'STAFF_ROLE' => implode(',', $jobPositionDescription),
				'STAFF_DEPARTMENT' => $currentStaff['StaffDepartment'],
				'STAFF_CAMPUS' => $currentStaff['StaffCampusDescription'],
				'STAFF_PHONE' => $currentStaff['StaffOccupPhone'],
				'STAFF_EMAIL' => $currentStaff['StaffOccupEmail'],
			));

			$this->getPage()->parse('staff_block', 'staff', true);
		}
	}

    public function export(Report_Exporter $exporter, $format = 'csv') {
        // function stub to appease interpreter
        // can be implemented if export required.
    }
}



