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

abstract class Base {

    /**
     * MSSQL db handler
     *
     * @var MSSQL
     */
    protected $db = null;

    /**
     * Constructor
     *
     * @param MSSQL $db
     */
    public function __construct(MSSQL $db) {
        $this->setDb($db);

        $this->setup();
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
    abstract public function getData();

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
} 