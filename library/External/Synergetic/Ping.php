<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_Synergetic_Ping implements External_PingInterface
{
    use External_MssqlPingTrait;

    /**
     * @var MSSQL
     */
    protected $database;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
    }

    /**
     * @inheritdoc
     */
    public function getVersion()
    {
        try {
            $version = $this->getDatabase()->getOne("SELECT TOP 1 [VALUE] FROM Config WHERE Key1 = 'System' AND Key2 = 'Network' AND Key3 = 'Version' AND Key4 = 'DataStructure'");
        } catch (\Exception $e) {
            // If all else fails we will default to reporting version 67 as this is the
            // baseline functionality level for the Synergetic connector

            return 67;
        }

        return $version;
    }

    /**
     * Set database
     *
     * @param $database
     */
    private function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * Get database
     *
     * @return MSSQL
     */
    protected function getDatabase()
    {
        return $this->database;
    }
}