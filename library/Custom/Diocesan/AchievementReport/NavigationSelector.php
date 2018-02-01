<?php
/**
 * ReportSelector
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport;

use Schoolbox\User;
use Schoolbox\Campus;
use MSSQL;

class NavigationSelector
{

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
     * User
     *
     * @var User
     */
    private $user = null;

    /**
     * Has NCEA records
     *
     * @var bool
     */
    private $hasNcea = false;

    /**
     * Has IB records
     *
     * @var bool
     */
    private $hasIb = false;

    /**
     * Has School Grades
     *
     * @var bool
     */
    private $hasSchoolGrades = false;

    /**
     * Available navigation options
     *
     * @var array
     */
    private $availableNavigation = array();

    /**
     * Default report information
     *
     * @var null
     */
    private $defaultReport = null;

    /**
     * Constructor
     *
     * @param $db
     * @param User $user
     * @param $externalUser
     */
    public function __construct($db, User $user, $externalUser)
    {
        $this->setExternalUser($externalUser);
        $this->setDb($db);
        $this->setUser($user);

        /* Determine navigation options
         */
        $this->navigationData();
    }

    /**
     * Determine default report
     **/
    public function determineDefaultReport()
    {
        $this->setDefaultReport(array('error' => 'Unknown default report.'));

        if (($this->getUser()->getYear() >= 1) && ($this->getUser()->getYear() <= 6)) {
            $this->setDefaultReport('comingSoon');
        }
        elseif (($this->getUser()->getYear() >= 7) && ($this->getUser()->getYear() <= 10)) {
            $this->setDefaultReport('schoolGrades');
        }
        /* NCEA for year 11 specifically
         */
        elseif ($this->getUser()->getYear() == 11) {
            if ($this->getHasNcea()) {
                $this->setDefaultReport('ncea');
            } else {
                $this->setDefaultReport(array('error' => 'Year 11 student not in NCEA.'));
            }
        } /* Need to decide whether this is an IB or an NCEA student
         */
        elseif (($this->getUser()->getYear() >= 12) && ($this->getUser()->getYear() <= 13)) {
            if ($this->getHasIb()) {
                $this->setDefaultReport('ib');
            } elseif ($this->getHasNcea()) {
                $this->setDefaultReport('ncea');
            } else {
                $this->setDefaultReport(array('error' => 'Year 12/13 student not in NCEA or IB.'));
            }
        }
    }

    /**
     * Checks to see how many IB records exist for a person
     * Checks to see how many NCEA records exist for a person
     *
     * Remembers in a boolean format
     *
     * Facade method
     */
    private function navigationData()
    {
        $availableNavigation = array();

        /* Conditionally add NCEA option
         */
        if ($this->hasNcea()) {
            $this->setHasNcea(true);
            $availableNavigation['ncea'] = 'NCEA';
        }

        /* Conditionally add IB option
         */
        if ($this->hasIb()) {
            $this->setHasIb(true);
            $availableNavigation['ib'] = 'IB';
        }

        /* School Grades always available
         */
        if ($this->hasSchoolGrades()) {
            $this->setHasSchoolGrades(true);
            $availableNavigation['schoolgrades'] = 'School Grades';
        }

        $this->setAvailableNavigation($availableNavigation);
    }

    /**
     * Find out if they are in the NCEA table
     */
    private function hasSchoolGrades()
    {
        $externalUser = $this->getExternalUser();
        $externalUserId = $externalUser['StudentID'];

        $query = "
            SELECT
                TOP 1 COUNT(*)
            FROM
                dbo.uvSchoolBoxSchoolGradeResults
            WHERE
                ID = '{$externalUserId}'
        ";

        $count = $this->getDb()->getRow($query);

        return $count['computed'];
    }

    /**
     * Find out if they are in the NCEA table
     */
    private function hasNcea()
    {
        $externalUser = $this->getExternalUser();
        $externalUserId = $externalUser['StudentID'];

        $query = "
            SELECT
                TOP 1 COUNT(*)
            FROM
                dbo.uvSchoolBoxNCEAResults
            WHERE
                ID = '{$externalUserId}'
        ";

        $count = $this->getDb()->getRow($query);

        return $count['computed'];
    }

    /**
     * Find out if they are in the IB table
     */
    private function hasIb()
    {
        $externalUser = $this->getExternalUser();
        $externalUserId = $externalUser['StudentID'];

        $query = "
            SELECT
                TOP 1 COUNT(*)
            FROM
                dbo.uvSchoolBoxIBResults
            WHERE
                ID = '{$externalUserId}'
        ";

        $count = $this->getDb()->getRow($query);

        return $count['computed'];
    }

    /* Getters/Setters
     */

    /**
     * Set db
     *
     * @param \MSSQL $db
     */
    private function setDb($db)
    {
        $this->db = $db;
    }

    /**
     * Get db
     *
     * @return \MSSQL
     */
    private function getDb()
    {
        return $this->db;
    }

    /**
     * Set externalUser
     *
     * @param array $externalUser
     */
    private function setExternalUser($externalUser)
    {
        $this->externalUser = $externalUser;
    }

    /**
     * Get externalUser
     *
     * @return array
     */
    private function getExternalUser()
    {
        return $this->externalUser;
    }

    /**
     * Set user
     *
     * @param \Schoolbox\User $user
     */
    private function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * Get user
     *
     * @return \Schoolbox\User
     */
    private function getUser()
    {
        return $this->user;
    }

    /**
     * Set hasIb
     *
     * @param boolean $hasIb
     */
    private function setHasIb($hasIb)
    {
        $this->hasIb = $hasIb;
    }

    /**
     * Get hasIb
     *
     * @return boolean
     */
    private function getHasIb()
    {
        return $this->hasIb;
    }

    /**
     * Set hasNcea
     *
     * @param boolean $hasNcea
     */
    private function setHasNcea($hasNcea)
    {
        $this->hasNcea = $hasNcea;
    }

    /**
     * Get hasNcea
     *
     * @return boolean
     */
    private function getHasNcea()
    {
        return $this->hasNcea;
    }

    /**
     * Set default report
     *
     * @param null $defaultReport
     */
    private function setDefaultReport($defaultReport)
    {
        $this->defaultReport = $defaultReport;
    }

    /**
     * Get default report
     *
     * @return null
     */
    public function getDefaultReport()
    {
        return $this->defaultReport;
    }

    /**
     * Set available navigation
     *
     * @param array $availableNavigation
     */
    private function setAvailableNavigation($availableNavigation)
    {
        $this->availableNavigation = $availableNavigation;
    }

    /**
     * Get available navigation
     *
     * @return array
     */
    public function getAvailableNavigation()
    {
        return $this->availableNavigation;
    }

    /**
     * Set has school grades
     *
     * @param boolean $hasSchoolGrades
     */
    private function setHasSchoolGrades($hasSchoolGrades)
    {
        $this->hasSchoolGrades = $hasSchoolGrades;
    }

    /**
     * Get has school grades
     *
     * @return boolean
     */
    private function getHasSchoolGrades()
    {
        return $this->hasSchoolGrades;
    }
}