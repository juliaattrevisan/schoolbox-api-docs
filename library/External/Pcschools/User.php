<?php
/**
 * PCSchools user connector
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_Pcschools_User implements External_UserInterface
{
    /**
     * @var MSSQL
     */
    private $database;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
    }

    /**
     * @return MSSQL
     */
    private function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param  MSSQL $database
     *
     * @return $this
     */
    private function setDatabase($database)
    {
        $this->database = $database;
        return $this;
    }
}
