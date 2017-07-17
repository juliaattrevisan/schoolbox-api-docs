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
     */
    public function getPortrait($externalUserId)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     */
    public function getParent($externalUserId)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     */
    public function getParentSync($externalUserId)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     */
    public function getParentEmails($synergyIds)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     */
    public function getStudent($externalUserId)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     */
    function getStaff($externalUserId)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     */
    public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     */
    function getStudentContactList($externalUserId)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
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
     */
    function getSubjectList(User $user)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     */
    function getEnrolledStudents($externalUserId)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     */
    function getEmail($externalUserId)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     */
    public function getStudentUserAccountInfo()
    {
        return $this->getDatabase()->getStudentUserAccountInfo();
    }

    /**
     * @inheritdoc
     */
    public function getParentUserAccountInfo()
    {
        return $this->getDatabase()->getParentUserAccountInfo();
    }

    /**
     * @inheritdoc
     */
    public function getStaffUserAccountInfo()
    {
        return $this->getDatabase()->getStaffUserAccountInfo();
    }

    /**
     * @inheritdoc
     */
    public function determineBoarderType($boarderValue)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     */
    public function getHousemasterByHouseId($houseID)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     */
    public function getStaffContactListExtended()
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
     */
    public function getCampusFromTimetable($staffID)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @inheritdoc
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
     * @param $database
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
