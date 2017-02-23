<?php
/************************************************************
 *														   *
 *		 Copyright (c) 2013 Alaress Pty Ltd 				*
 *														   *
 *	This file provides access to Denbigh class info		    *
 *														   *
 ************************************************************/

class External_Schoolbox_Class implements External_ClassInterface {

	protected $database;

	public function __construct () {
		$this->setdatabase( Database::getInstance() );
	}

    /**
	 * Gets a query for fetching a class's users out of the class_user table.
	 *
	 * This table should be used only to store students, but some schools use
	 * it to store teachers as well.
	 *
	 * @param  string       $class_code
	 * @return PDOStatement A statement: it requires a :type bound parameter for
	 *                      determining whether it should fetch students
	 *                      (Group::STUDENT_GROUP_TYPE) or teachers
	 *                      (Group::STAFF_GROUP_TYPE)
	 */
	protected function getClassQuery($class_code) {
		$codes = explode(',', $class_code);
		if (empty($codes)) {
			return false;
		}
		$statement = $this->getDatabase()->prepare("
			SELECT u.id, u.synergy_id, u.title, u.firstname, u.lastname, u.year, u.house
			FROM class c
			LEFT JOIN class_user cu ON cu.class_id = c.id
			LEFT JOIN user u ON u.id = cu.user_id
			LEFT JOIN groups g ON g.id = u.group_id
			WHERE c.code IN ({$this->getCodeParams($codes)}) AND g.group_type = :type
			ORDER BY u.lastname, u.firstname
		");
		return $this->bindCodes($statement, $codes);
	}

	/**
	 * Gets the query used to fetch a class's teachers out of the class_period
	 * table.
	 *
	 * Teachers shouldn't be stored in the class_user table, they should be
	 * stored here so that a single class can be taught by different teachers
	 * on different periods.
	 *
	 * @param  string       $class_code
	 * @return PDOStatement The statement for retrieving teachers
	 */
	protected function getTeacherQuery($class_code) {
		$codes = explode(',', $class_code);
		if (empty($codes)) {
			return false;
		}
		$statement = $this->getDatabase()->prepare("
			SELECT u.id, u.synergy_id, u.title, u.firstname, u.lastname, u.year, u.house
			FROM class c
			LEFT JOIN class_period cp ON c.id = cp.class_id
			INNER JOIN user u ON u.id = cp.teacher_id
			WHERE c.code IN ({$this->getCodeParams($codes)})
			ORDER BY u.lastname, u.firstname
		");
		return $this->bindCodes($statement, $codes);
	}

	/**
	 * Gets list of codes as a string that can be put into the IN part of a
	 * statement. The string contains bound parameters.
	 *
	 * @param  array  $codes
	 * @return string
	 */
	protected function getCodeParams($codes) {
		$query_parts = array();
		foreach ($codes as $i => $code) {
			$query_parts[] = ':code'.$i;
		}
		return implode(',', $query_parts);
	}

	/**
	 * Binds code parameters to a statement from getClassQuery() or
	 * getTeacherQuery().
	 *
	 * @param  PDOStatement $statement
	 * @param  array        $codes     A list of class codes.
	 * @return PDOStatement
	 */
	protected function bindCodes(PDOStatement $statement, $codes) {
		foreach ($codes as $i => $code) {
			$statement->bindValue(':code'.$i, $code);
		}
		return $statement;
	}

	public function getStudents($classCode) {
		$statement = $this->getClassQuery($classCode);
		if (!$statement) {
			return array();
		}
		$statement->bindValue(':type', Group::STUDENT_GROUP_TYPE);
		$statement->execute();
		$studentListAdaption = array();
		$student_id_list = array();
		foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $student) {
			// Make sure student isn't in list already.
			$student_sid = $student['synergy_id'];
			if (!array_key_exists($student_sid, $student_id_list)) {
				$student_id_list[$student_sid] = true;
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
		}

		return $studentListAdaption;
	}

	public function getTeacherName($classCode) {
		$teacher_id_list = array();

		// Retrieve all staff type users from class_user table
		if ($statement = $this->getClassQuery($classCode)) {
			$statement->bindValue(':type', Group::STAFF_GROUP_TYPE);
			$statement->execute();
			foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
				$teacher_id_list[] = $row['synergy_id'];
			}
		}

		// Retrieve teachers out of class_period table
		if ($statement = $this->getTeacherQuery($classCode)) {
			$statement->execute();
			foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
				$teacher_id_list[] = $row['synergy_id'];
			}
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
