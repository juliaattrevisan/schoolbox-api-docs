<?php
/************************************************************
 *														   *
 *		 Copyright (c) 2008 Lektec Consulting				*
 *														   *
 *	This file provides access to Denbigh class info		*
 *														   *
 ************************************************************/

class External_Denbigh_Class implements External_ClassInterface {

	private $database;

	public function __construct () {
		/* Set internal database */
		$this->setInternalDatabase( Database::getInstance() );

		/* Establish connection to external database */
		$database = new Database();

		$hostName = Config::get('external_db_host');
		$databaseName = Config::get('external_db_db');
		$username = Config::get('external_db_user');
		$password = Config::get('external_db_passwd');

		$database->connect("mysql:host=$hostName;dbname=$databaseName", $username, $password);

		$this->setExternalDatabase($database);
	}

	public function getStudents ($classCode) {
		$statement = $this->getExternalDatabase()->query("
			SELECT p.denbighID, p.surname, p.preferred_name, s.academicYear, s.house
			FROM Classes c
			LEFT JOIN Enrolments e ON e.classCode = c.classCode
			LEFT JOIN Students s ON s.peopleDenbighID = e.studentID
			LEFT JOIN People p ON p.denbighID = s.peopleDenbighID
			WHERE c.classCode = '$classCode'
			ORDER BY p.surname ASC
		");

		$studentList = $statement->fetchAll(PDO::FETCH_ASSOC);

		$studentAdaptionList = array();

		if ( !empty($studentList) ) {
			foreach ( $studentList as $student ) {
				$studentAdaption = array(
					'synergy_id' => $student['denbighID'],
					'lastname' => $student['surname'],
					'firstname' => $student['preferred_name'],
					'yearlevel' => $student['academicYear'],
					'house' => $student['house'],
					'StudentMedicalAlert' => '',
					'StudentSpecialNeedsFlag' => '',
				);

				$studentAdaptionList[] = $studentAdaption;
			}
		}

		return $studentAdaptionList;
	}

	public function getTeacherName ($classCode) {
		$statement = $this->getExternalDatabase()->query("
			SELECT p.denbighID
			FROM Classes c
			LEFT JOIN Staff s ON s.staffCode = c.staffCode
			LEFT JOIN People p ON p.denbighID = s.peopleDenbighID
			WHERE c.classCode = '$classCode'
		");

		while($teacher = $statement->fetch(PDO::FETCH_NUM)) {
			$teacherList[] = $teacher[0];
		}
		return $teacherList;
	}
	
	/**
	 * @param Database $database
	 * @return void
	 */
	private function setInternalDatabase (Database $internalDatabase) {
		$this->internalDatabase = $internalDatabase;
	}

	/**
	 * @return Database
	 */
	private function getInternalDatabase () {
		return $this->internalDatabase;
	}

	/**
	 * @param Database $database
	 * @return void
	 */
	private function setExternalDatabase (Database $externalDatabase) {
		$this->externalDatabase = $externalDatabase;
	}

	/**
	 * @return Database
	 */
	private function getExternalDatabase () {
		return $this->externalDatabase;
	}
}