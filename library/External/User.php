<?php
/**
 * @author    The Whole Company <support@alaress.com.au>
 * @copyright Copyright 2011-2017 (Hopefully RIP after that), Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\Redis;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\User;

class External_User implements External_UserInterface
{
    use External_CacheTrait;

    /** @var External_UserInterface $database */
    private $database;

    /** @var External_Offline_User $offline */
    private $offline;

    /** @var ConfigMapper $configMapper */
    private $configMapper;

    /**
     * Constructor
     *
     * @param string $databaseName
     * @throws Exception
     */
    public function __construct($databaseName = '')
    {
        $factory = new External_DatabaseFactory();
        $this->setConfigMapper(ConfigMapper::getInstance());
        $this->setDatabase($factory->buildUserConnector($databaseName));
        $this->setOffline(new External_Offline_User());
        $this->setCache(Redis::getInstance());
        $this->setTimeout($this->configMapper->get('external_cache_timeout'));
    }

    /**
     * @inheritdoc
     *
     * @param Integer $externalUserId
     * @return String
     */
    public function getPortrait($externalUserId)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * Used in search
     *
     * @param Integer $externalUserId
     * @return array
     */
    public function getParent($externalUserId)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * No usages found
     *
     * @param Integer $externalUserId
     * @return array
     */
    public function getParentSync($externalUserId)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     *
     * @param array $synergyIds
     * @return array
     */
    public function getParentEmails($synergyIds)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }


    /**
     * @inheritdoc
     *
     * @param Integer $externalUserId
     * @return array
     */
    public function getStudent($externalUserId)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     *
     * @param Integer $externalUserId
     * @return array
     */
    function getStaff($externalUserId)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     *
     * @param $yearlevel
     * @param $name
     * @param $boardingStatus
     * @param $form
     *
     * @internal param int $yearLevel
     * @return array
     */
    public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     *
     * @param string $externalUserId
     * @return array
     */
    function getStudentContactList($externalUserId)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     *
     * @return Integer
     */
    function getCurrentSemester($externalUserId = null)
    {
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
     * @inheritdoc
     *
     * @param User $user
     * @return array
     */
    function getSubjectList(User $user)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     *
     * @param Integer $externalUserId
     * @return array
     */
    function getEnrolledStudents($externalUserId)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     *
     * @param Integer $externalUserId
     * @return String
     */
    function getEmail($externalUserId)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     *
     * @todo Review usage of method
     * @return array
     */
    public function getStudentUserAccountInfo()
    {
        return $this->getDatabase()->getStudentUserAccountInfo();
    }

    /**
     * @inheritdoc
     *
     * @todo Review usage of method
     * @return array
     */
    public function getParentUserAccountInfo()
    {
        return $this->getDatabase()->getParentUserAccountInfo();
    }

    /**
     * @inheritdoc
     *
     * @todo Review usage of method
     * @return array
     */
    public function getStaffUserAccountInfo()
    {
        return $this->getDatabase()->getStaffUserAccountInfo();
    }

    /**
     * @inheritdoc
     *
     * @param $boarderValue
     * @return mixed
     */
    public function determineBoarderType($boarderValue)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     *
     * @param $houseID
     * @return mixed
     */
    public function getHousemasterByHouseId($houseID)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     *
     * @param array $externalUserIds
     * @return mixed
     */
    public function getHousemasterEmails($externalUserIds)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     *
     * @return array
     */
    public function getStaffContactListExtended()
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     *
     * @param int $staffID
     * @return array|bool
     */
    public function getCampusFromTimetable($staffID)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     *
     * @return array [['Code' => string, 'Description' => string], ...]
     */
    public function getStudentForms()
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @param $methodName
     * @param $argumentList
     * @return mixed
     */
    public function __call($methodName, $argumentList)
    {
        $database = $this->getDatabase();

        $methodReflection = new ReflectionMethod($database, $methodName);

        return $methodReflection->invokeArgs($database, $argumentList);
    }

    /**
     * @param External_UserInterface $database
     */
    private function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * @return External_UserInterface
     */
    private function getDatabase()
    {
        return $this->database;
    }

    /**
     * @return External_Offline_User
     */
    public function getOffline()
    {
        return $this->offline;
    }

    /**
     * @param External_Offline_User $offline
     */
    public function setOffline($offline)
    {
        $this->offline = $offline;
    }

    /**
     * @return ConfigMapper
     */
    public function getConfigMapper()
    {
        return $this->configMapper;
    }

    /**
     * @param ConfigMapper $configMapper
     */
    public function setConfigMapper($configMapper)
    {
        $this->configMapper = $configMapper;
    }
}
