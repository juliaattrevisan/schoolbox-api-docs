<?php
/************************************************************
 *														   *
 *		 Copyright (c) 2008 Lektec Consulting				*
 *														   *
 *	This file provides access to Denbigh class info		*
 *														   *
 ************************************************************/

class External_Schoolbox_Class implements External_ClassInterface {

	protected $database;

	public function __construct () {
		$this->setdatabase( Database::getInstance() );
	}

	public function getStudents ($classCode) {
		$statement = $this->getDatabase()->prepare("
			SELECT u.id, u.synergy_id, u.title, u.firstname, u.lastname, u.year, u.house
			FROM class c
			LEFT JOIN class_user cu ON cu.class_id = c.id
			LEFT JOIN user u ON u.id = cu.user_id
			LEFT JOIN groups g ON g.id = u.group_id
			WHERE c.code = :code
				AND g.group_type = :type
			ORDER BY u.lastname, u.firstname
		");

		$statement->execute( array(
			':code' => $classCode,
			':type' => Group::STUDENT_GROUP_TYPE
		) );

		$studentList = $statement->fetchAll(PDO::FETCH_ASSOC);

		$studentListAdaption  = array();

		foreach ( $studentList as $student ) {
			$studentListAdaption[] = array(
				'synergy_id' => $student['synergy_id'],
				'lastname' => $student['lastname'],
				'firstname' => $student['firstname'],
				'yearlevel' => $student['year'],
				'house' => $student['house'],
				'StudentMedicalAlert' => '',
				'StudentSpecialNeedsFlag' => '',
			);
		}

		return $studentListAdaption;
	}

	public function getTeacherName ($classCode) {
		$statement = $this->getDatabase()->prepare("
			SELECT u.synergy_id
			FROM class c
			LEFT JOIN class_user cu ON cu.class_id = c.id
			LEFT JOIN user u ON u.id = cu.user_id
			LEFT JOIN groups g ON g.id = u.group_id
			WHERE c.code = :code
				AND g.group_type = :type
		");

		$statement->execute( array(
			':code' => $classCode,
			':type' => Group::STAFF_GROUP_TYPE
		) );

		$teacher = $statement->fetch(PDO::FETCH_NUM);

		return $teacher;
	}
	
	/**
	 * @param Database $database
	 * @return void
	 */
	private function setDatabase (Database $database) {
		$this->database = $database;
	}

	/**
	 * @return Database
	 */
	private function getDatabase () {
		return $this->database;
	}
}