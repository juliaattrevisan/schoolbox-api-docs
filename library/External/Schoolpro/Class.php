<?php
/**
 * External_Schoolpro_Class
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Schoolpro_Class implements External_ClassInterface {

	/**
	 * An instance of MSSQL
	 *
	 * @var null
	 */
	private $db = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setDb(MSSQL::getInstance());
	}

	/**
	 * Get a list of students that are in a particular class
	 *
	 * @param $classCode
	 * @return array|bool
	 */
	public function getStudents($classCode) {
		$allStudents = array();

		/* Class Codes, when combined, are joined by a comma
		 * so split them out and combine request results
		 */
		$allClassCodes = explode(',', $classCode);

		foreach($allClassCodes as $currentClassCode) {
			$vars = array(
				'sbGetStudents',
				array(
					array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
					array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
					array('@pClassID', trim($currentClassCode), SQLINT4, false, false, -1),
				)
			);

			$result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

			if ($result['success'] && count($result['result'])) {
				foreach($result['result'] as $currentStudent) {
                    $medicalNoteStr = '';
                    
                    /* Get the medical data for him too
                     */
                    $medicalVars = array(
                        'sbGetStudentMedicalConditions',
                        array(
                            array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
                            array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
                            array('@pSchoolNum', $currentStudent['ID'], SQLINT4, false, false, -1),
                        )
                    );

                    $medicalResult = $this->getDb()->executeProcedure($medicalVars[0], $medicalVars[1]);

                    /* These arrive in a variety of formats
                     */
                    if ($medicalResult['success'] && count($medicalResult['result'])) {
                        foreach ($medicalResult['result'] as $currentCondition) {

                            /* Only include medical issues that are Applicable == 1
                             * Just use a basic message to hide the sensitive data,
                             * and bust out of the loop after the first positive result
                             */
                            if ($currentCondition['Applicable']) {
                                $medicalNoteStr = 'This student has medical conditions.';
                                break;
                            }
                        }
                    }

					$student = array(
						'synergy_id' => $currentStudent['ID'],
						'firstname' => $currentStudent['Firstname'],
						'lastname' => $currentStudent['Surname'],
						'yearlevel '=> $currentStudent['YearLevel'],
						'house' => $currentStudent['House'],
						'StudentMedicalAlert' => $medicalNoteStr,
						'StudentSpecialNeedsFlag' => '',
						'ClassCampus' => '',
					);

					$allStudents[] = $student;
				}
			}
		}
		return $allStudents;
	}

	/**
	 * Get the class' teacher's name
	 *
	 * Wait, what - it returns a list of unique teacher externalIds...?
	 *
	 * @param $classCode
	 * @return array|bool
	 */
	public function getTeacherName($classCode) {
		$allTeachers = array();

		$vars = array(
			'sbGetTeacherName',
			array(
				array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pClassID', $classCode, SQLINT4, false, false, -1),
			)
		);

		$result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

		if ($result['success'] && count($result['result'])) {
			foreach($result['result'] as $currentTeacher) {
				$allTeachers[] = $currentTeacher['ID'];
			}
		}

		return array_unique($allTeachers);
	}

	/* Getters/Setters
	 */

	/**
	 * Set db
	 *
	 * @param null $db
	 */
	private function setDb($db) {
		$this->db = $db;
	}

	/**
	 * Get db
	 *
	 * @return null
	 */
	private function getDb() {
		return $this->db;
	}
} 