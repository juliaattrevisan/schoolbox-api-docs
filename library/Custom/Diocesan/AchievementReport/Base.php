<?php
/**
 * Base
 *
 * All report subclasses that need external db connection should
 * inherit this class
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport;

use MSSQL;
use Alaress_Probe as Probe;

abstract class Base {

    /**
     * MSSQL db handler
     *
     * @var MSSQL
     */
    protected $db = null;

    /**
     * An externalUser array
     *
     * @var array
     */
    protected $externalUser = array();

    /**
     * Table heading
     *
     * @var string
     */
    protected $heading = '';

    /**
     * The year for which to obtain data
     *
     * @var string
     */
    protected $year = 0;

    /**
     * The current year
     *
     * @var string
     */
    protected $currentYear = 0;

    /**
     * A bunch of letters to obtain
     *
     * @var array
     */
    protected $letterRegistry = array('N', 'A', 'M', 'E');

    /**
     * Constructor
     *
     * @param $externalUser
     * @param MSSQL $db
     * @param $year
     */
    public function __construct($externalUser, MSSQL $db, $year) {
        $this->setExternalUser($externalUser);
        $this->setDb($db);
        $this->setYear($year);
        $this->setCurrentYear(date('Y'));

        $this->setup();
    }

    /**
     * Get a list of all the views with 'uv' as a prefix
     */
    protected function debug() {
        $query = "
            SELECT TABLE_CATALOG,
            TABLE_SCHEMA,
            TABLE_NAME,
            TABLE_TYPE
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_NAME LIKE 'uv%'
            AND TABLE_TYPE = 'VIEW'
        ";

        $stuff = $this->getDb()->getAll($query);

        new Probe($stuff);
    }

    /**
     * Hook method
     *
     * @return mixed
     */
    abstract protected function setup();

    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    abstract public function findData();

    /**
     * Get the primary key out of $externalUser array
     *
     * @return mixed
     */
    protected function getExternalUserId() {
        return $this->externalUser['StudentID'];
    }

    /* Getters/Setters
     */

    /**
     * Set db
     *
     * @param \MSSQL $db
     */
    protected function setDb($db)
    {
        $this->db = $db;
    }

    /**
     * Get db
     *
     * @return \MSSQL
     */
    protected function getDb()
    {
        return $this->db;
    }

    /**
     * Set heading
     *
     * @param string $heading
     */
    protected function setHeading($heading)
    {
        $this->heading = $heading;
    }

    /**
     * Get heading
     *
     * @return string
     */
    public function getHeading()
    {
        return $this->heading;
    }

    /**
     * Set externalUser
     *
     * @param array $externalUser
     */
    protected function setExternalUser($externalUser)
    {
        $this->externalUser = $externalUser;
    }

    /**
     * Get externalUser
     *
     * @return array
     */
    protected function getExternalUser()
    {
        return $this->externalUser;
    }

    /**
     * @param string $year
     */
    protected function setYear($year)
    {
        $this->year = $year;
    }

    /**
     * @return string
     */
    protected function getYear()
    {
        return $this->year;
    }

    /**
     * Set current year
     *
     * @param string $currentYear
     */
    protected function setCurrentYear($currentYear)
    {
        $this->currentYear = $currentYear;
    }

    /**
     * Get current year
     *
     * @return string
     */
    protected function getCurrentYear()
    {
        return $this->currentYear;
    }

    /**
     * Set letter registry
     *
     * @param array $letterRegistry
     */
    protected function setLetterRegistry($letterRegistry)
    {
        $this->letterRegistry = $letterRegistry;
    }

    /**
     * Get letter registry
     *
     * @return array
     */
    public function getLetterRegistry()
    {
        return $this->letterRegistry;
    }
}