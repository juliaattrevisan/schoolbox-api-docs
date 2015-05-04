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

use Alaress_Probe as Probe;
use Schoolbox\User;
use MSSQL;

class ReportSelector {

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
     * Constructor
     *
     * @param $db
     * @param User $user
     * @param $externalUser
     */
    public function __construct($db, User $user, $externalUser) {
        $this->setExternalUser($externalUser);
        $this->setDb($db);
        $this->setUser($user);
    }

    /**
     * Determine default report
     *
     * @return string
     */
    public function determine() {
        if ($this->getUser()->getDefaultCampus()->getExtTimetableCode() == 'JNR') {
            if (($this->getUser()->getYear() >= 1) && ($this->getUser()->getYear() <= 6)) {
                /* TODO: Standardised Test Scores
                 */
                die('Standardised Test Scores not available.');
            }
        }
        elseif ($this->getUser()->getDefaultCampus()->getExtTimetableCode() == 'SNR') {

            /* School Grades by default
             */
            if (($this->getUser()->getYear() >= 7) && ($this->getUser()->getYear() <= 10)) {
                return 'schoolGrades';
            }
            /* NCEA for year 11 specifically
             */
            elseif ($this->getUser()->getYear() == 11) {
                if ($this->isNcea()) {
                    return 'ncea';
                }
                else {
                    /* TODO: Year 11 student not enrolled in NCEA
                     */
                    die('Year 11 student not in NCEA.');
                }
            }
            /* Need to decide whether this is an IB or an NCEA student
             */
            elseif (($this->getUser()->getYear() >= 12) && ($this->getUser()->getYear() <= 13)) {
                if ($this->isIb()) {
                    return 'ib';
                }
                elseif ($this->isNcea()) {
                    return 'ncea';
                }
                else {
                    /* TODO: Year 12/13 student not in either IB or NCEA
                     */
                    die('Year 12/13 student not enrolled in either.');
                }
            }
        }
    }

    /**
     * Find out if they are in the NCEA table
     */
    private function isNcea() {
        $externalUser = $this->getExternalUser();
        $externalUserId = $externalUser['StudentID'];

        $query = "
            SELECT
                COUNT(*)
            FROM
                SynergyOne.dbo.uvSchoolBoxNCEAResults
            WHERE
                ID = '{$externalUserId}'
        ";

        $count = $this->getDb()->getRow($query);

        return $count['computed'];
    }

    /**
     * Find out if they are in the IB table
     */
    private function isIb() {
        $externalUser = $this->getExternalUser();
        $externalUserId = $externalUser['StudentID'];

        $query = "
            SELECT
                COUNT(*)
            FROM
                SynergyOne.dbo.uvSchoolBoxIBResults
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
}