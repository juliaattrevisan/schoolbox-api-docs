<?php
/**
 * @author      Damian Sloane <dsloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Alaress\Redis;
use Schoolbox\User;
use Schoolbox\Config\ConfigMapper;

class External_Document implements External_DocumentInterface
{
    use External_CacheTrait;

    /** @var External_DocumentInterface */
    private $database;

    /** @var External_Offline_Document */
    private $offline;

    /** @var ConfigMapper $configMapper */
    private $configMapper;

    /**
     * Constructor
     *
     * @throws Exception
     * @param String $databaseName
     */
    public function __construct($databaseName = '')
    {
        $factory = new External_DatabaseFactory();
        $this->setConfigMapper(ConfigMapper::getInstance());
        $this->setDatabase($factory->buildDocumentConnector($databaseName));
        $this->setOffline(new External_Offline_Document());
        $this->setCache(Redis::getInstance());
        $this->setTimeout($this->configMapper->get('external_cache_timeout'));
    }

    /**
     * @inheritdoc
     *
     * @param User $student
     * @return array
     */
    public function getAcademicReportsForStudent(User $student)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    // Getters & Setters

    /**
     * @return External_DocumentInterface
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param External_DocumentInterface $database
     * @return self
     */
    public function setDatabase($database)
    {
        $this->database = $database;
        return $this;
    }

    /**
     * @return External_Offline_Document
     */
    public function getOffline()
    {
        return $this->offline;
    }

    /**
     * @param External_Offline_Document $offline
     * @return self
     */
    public function setOffline($offline)
    {
        $this->offline = $offline;
        return $this;
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