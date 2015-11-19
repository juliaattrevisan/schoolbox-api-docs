<?php
/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 20/10/11
 * Time: 4:40 PM
 * To change this template use File | Settings | File Templates.
 */

use Alaress\Redis;

class External_User implements External_UserInterface {

    use External_CacheTrait;

	private $database;

	/**
	 * Constructor
	 *
	 * @throws Exception
	 * @param String $databaseName
	 */
	public function __construct($databaseName = '') {
		$factory = new External_DatabaseFactory();
		$this->setDatabase($factory->buildUserConnector($databaseName));
        $this->setCache(Redis::getInstance());
        $this->setTimeout(Config::get('external_cache_timeout'));
	}

    /**
     * Returns a valid readable path to the user's portrait
     *
     * @param Integer $externalUserId
     * @return String
     */
    public function getPortrait ($externalUserId) {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

	/**
	 * Used in search
	 *
	 * @param Integer $externalUserId
	 * @return Array
	 */
	public function getParent ($externalUserId) {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
	}

	/**
	 * No usages found
	 *
	 * @param Integer $externalUserId
	 * @return Array
	 */
	public function getParentSync ($externalUserId) {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
	}

    /**
     * Returns a list of emails for parents of the listed students
     *
     * @param array $synergyIds
     * @return array
     */
    public function getParentEmails($synergyIds) {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }


	/**
	 * Used in search
	 *
	 * @param Integer $externalUserId
	 * @return Array
	 */
	public function getStudent ($externalUserId) {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
	}

	/**
	 * Used in search
	 *
	 * @param Integer $externalUserId
	 * @return Array
	 */
	function getStaff ($externalUserId) {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
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
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
	}

	/**
	 * Compiles a list of contacts relevant to a student
	 *
	 * @param Integer $externalUserId
	 * @return Array
	 */
	function getStudentContactList ($externalUserId) {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
	}

	/**
	 * Used in report module, determines the current semester
	 *
	 * @return Integer
	 */
	function getCurrentSemester ($externalUserId = null) {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
	}

    /**
     * {@inheritdoc}
     */
    public function getCurrentYear($externalUserId = null)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

	/**
	 * Retrieves a teacher or student's subject list
	 *
	 * @param Integer $externalUserId
	 * @param Integer $userID
	 * @return Array
	 */
	function getSubjectList ($externalUserId, $userID) {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
	}

	/**
	 * Retrieves a parent's enrolled students
	 *
	 * @param Integer $externalUserId
	 * @return Array
	 */
	function getEnrolledStudents ($externalUserId) {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
	}

	/**
	 * Return a user's email
	 *
	 * @param Integer $externalUserId
	 * @return String
	 */
	function getEmail ($externalUserId) {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
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
	 * {@inheritdoc}
	 */
	public function getStudentUserAccountIterator()
	{
		return $this->getDatabase()->getStudentUserAccountIterator();
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
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * Return the housemaster of the specified house
     *
     * @param $houseID
     * @return mixed
     */
    public function getHousemasterByHouseId ($houseID) {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * Get contact list for staff directory
     *
     * @return array
     */
    public function getStaffContactListExtended() {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * List of campuses a staff member is involved in based on timetable
     *
     * @param int $staffID
     * @return array|bool
     */
    public function getCampusFromTimetable($staffID)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * Retrieves a list of forms that a student may be in.
     *
     * @return array [['Code' => string, 'Description' => string], ...]
     */
    public function getStudentForms()
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
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
