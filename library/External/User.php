<?php
/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 20/10/11
 * Time: 4:40 PM
 * To change this template use File | Settings | File Templates.
 */
 
class External_User {
	private $database;

	public function __construct ($databaseName = '') {
		if ( empty($databaseName) ) {
			$databaseName = Config::get('external_type');
		}

		$databaseClass = 'External_'.ucfirst($databaseName).'_User';

		if ( class_exists($databaseClass, true) ) {
			$database = new $databaseClass;

			$this->setDatabase( $database );
		}
		else {
			throw Exception;
		}
	}

	/**
	 * Used in search
	 *
	 * @param Integer $externalUserId
	 * @return Array
	 */
	public function getParent ($externalUserId) {
		return $this->getDatabase()->getParent($externalUserId);
	}

	/**
	 * No usages found
	 *
	 * @param Integer $externalUserId
	 * @return Array
	 */
	public function getParentSync ($externalUserId) {
		return $this->getDatabase()->getParentSync($externalUserId);
	}

	/**
	 * Used in search
	 *
	 * @param Integer $externalUserId
	 * @return Array
	 */
	public function getStudent ($externalUserId) {
		return $this->getDatabase()->getStudent($externalUserId);
	}

	/**
	 * Used in search
	 *
	 * @param Integer $externalUserId
	 * @return Array
	 */
	function getStaff ($externalUserId) {
		return $this->getDatabase()->getStaff($externalUserId);
	}

	/**
	 * Used in report module
	 *
	 * @param Integer $yearLevel
	 * @return Array
	 */
	public function getStudentContactListExtended ($yearLevel) {
		return $this->getDatabase()->getStudentContactListExtended($yearLevel);
	}

	/**
	 * Used in search
	 *
	 * @param Integer $externalUserId
	 * @return Array
	 */
	function getStudentContactList ($externalUserId) {
		return $this->getDatabase()->getStudentContactList($externalUserId);
	}

	/**
	 * Used in report module
	 *
	 * @return Integer
	 */
	function getCurrentSemester () {
		return $this->getDatabase()->getCurrentSemester();
	}

	/**
	 * Used in login
	 *
	 * @param Integer $externalUserId
	 * @param Integer $userID
	 * @return Array
	 */
	function getSubjectList ($externalUserId, $userID) {
		return $this->getDatabase()->getSubjectList($externalUserId, $userID);
	}

	/**
	 * Used in login
	 *
	 * @param Integer $externalUserId
	 * @return Array
	 */
	function getEnrolledStudents ($externalUserId) {
		return $this->getDatabase()->getEnrolledStudents($externalUserId);
	}

	/**
	 * Used in login
	 *
	 * @param Integer $externalUserId
	 * @return String
	 */
	function getEmail ($externalUserId) {
		return $this->getDatabase()->getEmail($externalUserId);
	}

	/**
	 * No usages found
	 *
	 * @return Array
	 */
	public function getStudentUserAccountInfo () {
		return $this->getDatabase()->getStudentUserAccountInfo();
	}

	/**
	 * No usages found
	 *
	 * @return Array
	 */
	public function getParentUserAccountInfo () {
		return $this->getDatabase()->getParentUserAccountInfo();
	}

	/**
	 * No usages found
	 *
	 * @return Array
	 */
	public function getStaffUserAccountInfo () {
		return $this->getDatabase()->getStaffUserAccountInfo();
	}

	public function __call ($methodName, $argumentList) {
		$database = $this->getDatabase();

		$methodReflection = new ReflectionMethod($database, $methodName);

		return $methodReflection->invokeArgs($database, $argumentList);
	}

	private function setDatabase ($database) {
		$this->database = $database;
	}

	private function getDatabase () {
		return $this->database;
	}

}
