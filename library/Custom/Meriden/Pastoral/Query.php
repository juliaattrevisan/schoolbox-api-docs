<?php
/**
 * Queries for the Meriden pastoral system.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Custom\Meriden\Pastoral;

use MSSQL;

class Query
{
    /**
     * @var MSSQL
     */
    protected $database;

    /**
     * Constructor
     *
     * @param MSSQL $database
     */
    public function __construct(MSSQL $database)
    {
        $this->setDatabase($database);
    }

    /**
     * @return MSSQL
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param  MSSQL $database
     *
     * @return $this
     */
    public function setDatabase($database)
    {
        $this->database = $database;
        return $this;
    }
}
