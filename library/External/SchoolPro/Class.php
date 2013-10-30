<?php
/**
 * External_SchoolPro_Class
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_SchoolPro_Class implements External_ClassInterface {

	/**
	 * Get a list of students that are in a particular class
	 *
	 * @param $classCode
	 * @return array|bool
	 */
	public function getStudents($classCode) {
		$vars = array(
			'sbGetStudents',
			array(
				array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pClassID', $classCode, SQLINT4, false, false, -1),
			)
		);

		$result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

		if ($result['success'] && count($result['result'])) {
			$allStudents = array();

			foreach($result['result'] as $currentStudent) {
				$student = array(
					'synergy_id' => $currentStudent['ID'],
					'lastname' => $currentStudent['Firstname'],
					'firstname' => $currentStudent['Surname'],
					'yearlevel '=> $currentStudent['YearLevel'],
					'house' => $currentStudent['House'],
					'StudentMedicalAlert' => '',
					'StudentSpecialNeedsFlag' => '',
					'ClassCampus' => '',
				);

				$allStudents[] = $student;
			}

			return $allStudents;
		}

		return false;
	}

	/**
	 * Get the class' teacher's name
	 *
	 * @param $classCode
	 */
	public function getTeacherName($classCode) {
		$vars = array(
			'sbGetTeacherName',
			array(
				array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pClassID', $classCode, SQLINT4, false, false, -1),
			)
		);

		$result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

		new \Alaress_Probe($result);

		if ($result['success']) {
		}
		else {
		}
	}
} 