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

	/**
	 * Gets the query used by both getStudents() and getTeacherName().
	 *
	 * @param  string $class_code
	 * @return PDOStatement
	 */
	protected function getQuery($class_code) {
		$codes = explode(',', $class_code);
		if (empty($codes)) {
			return false;
		}

		// Put each code into a param. Argh
		$query_parts = array();
		foreach ($codes as $i => $code) {
			$query_parts[] = ':code'.$i;
		}
		$code_query = implode(',', $query_parts);
		$statement = $this->getDatabase()->prepare("
			SELECT u.id, u.synergy_id, u.title, u.firstname, u.lastname, u.year, u.house
			FROM class c
			LEFT JOIN class_user cu ON cu.class_id = c.id
			LEFT JOIN user u ON u.id = cu.user_id
			LEFT JOIN groups g ON g.id = u.group_id
			WHERE c.code IN ($code_query) AND g.group_type = :type
			ORDER BY u.lastname, u.firstname
		");
		foreach ($codes as $i => $code) {
			$statement->bindValue(':code'.$i, $code);
		}
		return $statement;
	}

	public function getStudents($classCode) {
		$statement = $this->getQuery($classCode);
		if (!$statement) {
			return array();
		}
		$statement->bindValue(':type', Group::STUDENT_GROUP_TYPE);
		$statement->execute();
		$studentListAdaption = array();
		foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $student) {
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

	public function getTeacherName($classCode) {
		$statement = $this->getQuery($classCode);
		if (!$statement) {
			return false;
		}
		$statement->bindValue(':type', Group::STAFF_GROUP_TYPE);
		$statement->execute();
		$teacher_id_list = array();
		foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$teacher_id_list[] = $row['synergy_id'];
		}
		return array_unique($teacher_id_list);
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