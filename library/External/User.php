<?php
/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 20/10/11
 * Time: 4:40 PM
 * To change this template use File | Settings | File Templates.
 */

class External_User implements External_UserInterface {
	private $database;

	/**
	 * Constructor
	 *
	 * @throws Exception
	 * @param String $databaseName
	 */
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
			error(22);
		}
	}

    /**
     * Returns a valid readable path to the user's portrait
     *
     * @param Integer $externalUserId
     * @return String
     */
    public function getPortrait ($externalUserId) {
        return $this->getDatabase()->getPortrait($externalUserId);
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
     * Returns a list of emails for parents of the listed students
     *
     * @param array $synergyIds
     * @return array
     */
    public function getParentEmails($synergyIds) {
        return $this->getDatabase()->getParentEmails($synergyIds);
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
	 * @param $yearlevel
	 * @param $name
	 * @param $boardingStatus
	 * @param $form
	 *
	 * @internal param int $yearLevel
	 * @return Array
	 */
	public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form) {
		return $this->getDatabase()->getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form);
	}

	/**
	 * Compiles a list of contacts relevant to a student
	 *
	 * @param Integer $externalUserId
	 * @return Array
	 */
	function getStudentContactList ($externalUserId) {
		return $this->getDatabase()->getStudentContactList($externalUserId);
	}

	/**
	 * Used in report module, determines the current semester
	 *
	 * @return Integer
	 */
	function getCurrentSemester () {
		return $this->getDatabase()->getCurrentSemester();
	}

	/**
	 * Retrieves a teacher or student's subject list
	 *
	 * @param Integer $externalUserId
	 * @param Integer $userID
	 * @return Array
	 */
	function getSubjectList ($externalUserId, $userID) {
		return $this->getDatabase()->getSubjectList($externalUserId, $userID);
	}

	/**
	 * Retrieves a parent's enrolled students
	 *
	 * @param Integer $externalUserId
	 * @return Array
	 */
	function getEnrolledStudents ($externalUserId) {
		return $this->getDatabase()->getEnrolledStudents($externalUserId);
	}

	/**
	 * Return a user's email
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
	 * @todo Review usage of method
	 * @return Array
	 */
	public function getStudentUserAccountInfo () {
		return $this->getDatabase()->getStudentUserAccountInfo();
	}

	/**
	 * No usages found
	 *
	 * @todo Review usage of method
	 * @return Array
	 */
	public function getParentUserAccountInfo () {
		return $this->getDatabase()->getParentUserAccountInfo();
	}

	/**
	 * No usages found
	 *
	 * @todo Review usage of method
	 * @return Array
	 */
	public function getStaffUserAccountInfo () {
		return $this->getDatabase()->getStaffUserAccountInfo();
	}

    /**
     * Determine the boarder type of a student
     *
     * @param $boarderValue
     * @return mixed
     */
    public function determineBoarderType ($boarderValue) {
        return $this->getDatabase()->determineBoarderType($boarderValue);
    }

    /**
     * Return the housemaster of the specified house
     *
     * @param $houseID
     * @return mixed
     */
    public function getHousemasterByHouseId ($houseID) {
        return $this->getDatabase()->getHousemasterByHouseId($houseID);
    }

	public function __call ($methodName, $argumentList) {
		$database = $this->getDatabase();

		$methodReflection = new ReflectionMethod($database, $methodName);

		return $methodReflection->invokeArgs($database, $argumentList);
	}

	private function setDatabase ($database) {
		$this->database = $database;
	}

    /**
     * @return External_UserInterface
     */
    private function getDatabase () {
		return $this->database;
	}

}
