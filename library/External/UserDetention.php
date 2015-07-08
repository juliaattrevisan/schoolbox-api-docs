<?php
/**
 * Wrapper class for user detention external connectors.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\Redis;

class External_UserDetention implements External_UserDetentionInterface {

    use External_CacheTrait;

    /**
     * @var External_UserDetentionInterface
     */
    protected $database;

    /**
     * Constructor
     *
     * @throws Exception
     * @param String $databaseName
     */
    public function __construct($databaseName = '') {
        $factory = new External_DatabaseFactory();
        $this->setDatabase($factory->buildUserDetentionConnector($databaseName));
        $this->setCache(Redis::getInstance());
        $this->setTimeout(Config::get('external_cache_timeout'));
    }

    /**
     * Retrieves a user's awards.
     *
     * @param mixed $externalId The student's external ID.
     * @param int   $year
     * @param int   $semester
     *
     * @return array
     */
    public function getAwards(
        $externalId,
        $year,
        $semester
    ) {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * Retrieves a user's detentions.
     *
     * @param mixed    $externalId The student's external ID.
     * @param DateTime $fromDate   The date to fetch from, or null to get all
     *                             in the past.
     * @param DateTime $toDate     The date to fetch until, or null to get all
     *                             in the future.
     *
     * @return array
     */
    public function getDetentions(
        $externalId,
        DateTime $fromDate = null,
        DateTime $toDate = null
    ) {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * Retrieves a user's absences that do not have an absence code.
     *
     * @param mixed    $externalId The student's external ID.
     * @param DateTime $fromDate   The date to fetch from, or null to get all
     *                             in the past.
     * @param DateTime $toDate     The date to fetch until, or null to get all
     *                             in the future.
     *
     * @return array
     */
    public function getAbsences(
        $externalId,
        DateTime $fromDate = null,
        DateTime $toDate = null
    ) {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * @param External_UserDetentionInterface $database
     * @return $this
     */
    private function setDatabase($database)
    {
        $this->database = $database;
        return $this;
    }

    /**
     * Used by External_CacheTrait->getCachedResult()
     *
     * @return External_UserDetentionInterface
     */
    private function getDatabase()
    {
        return $this->database;
    }
}
