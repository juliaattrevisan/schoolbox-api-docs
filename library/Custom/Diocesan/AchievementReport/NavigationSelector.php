<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Custom\Diocesan\AchievementReport;

use Custom\Diocesan\AchievementReport\Comments\ValidJuniorSchoolStudent;
use Schoolbox\User;
use MSSQL;

/**
 * Class NavigationSelector
 *
 * @package Custom\Diocesan\AchievementReport
 */
class NavigationSelector
{

    /**
     * Navigation keys
     */
    public const STANDARDISEDTEST_KEY = 'standardisedTest';
    public const SCHOOLGRADES_KEY = 'schoolGrades';
    public const NCEA_KEY = 'ncea';
    public const IB_KEY = 'ib';
    public const COMMENTS_KEY = 'comments';
    public const COCURRICULAR_KEY = 'coCurricular';


    /**
     * MSSQL db handler
     *
     * @var MSSQL
     */
    private $db;


    /**
     * An externalUser array
     *
     * @var array
     */
    private $externalUser;


    /**
     * User
     *
     * @var User
     */
    private $user;


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
     * Has Comments
     *
     * @var bool
     */
    private $hasComments = false;


    /**
     * Has Co-Curricular activities
     *
     * @var bool
     */
    private $hasCoCurricular = false;


    /**
     * Available navigation options
     *
     * @var array
     */
    private $availableNavigation = [];


    /**
     * Default report information
     *
     * @var null
     */
    private $defaultReport;


    /**
     * Constructor
     *
     * @param MSSQL $db
     * @param User $user
     * @param array $externalUser
     */
    public function __construct(MSSQL $db, User $user, array $externalUser)
    {
        $this->externalUser = $externalUser;
        $this->db = $db;
        $this->user = $user;

        /* Determine navigation options
         */
        $this->navigationData();
    }


    /**
     * Determine default report
     **/
    public function determineDefaultReport(): void
    {
        $this->defaultReport = ['error' => 'Unknown default report.'];

        if (($this->user->getYear() >= 1) && ($this->user->getYear() <= 6)) {
            $this->defaultReport = self::STANDARDISEDTEST_KEY;
        }
        elseif (($this->user->getYear() >= 7) && ($this->user->getYear() <= 10)) {
            $this->defaultReport = self::SCHOOLGRADES_KEY;
        }
        /* NCEA for year 11 specifically
         */
        elseif ($this->user->getYear() == 11) {
            if ($this->hasNcea) {
                $this->defaultReport = self::NCEA_KEY;
            } else {
                $this->defaultReport = ['error' => 'Year 11 student not in NCEA.'];
            }
        } /* Need to decide whether this is an IB or an NCEA student
         */
        elseif (($this->user->getYear() >= 12) && ($this->user->getYear() <= 13)) {
            if ($this->hasIb) {
                $this->defaultReport = self::IB_KEY;
            } elseif ($this->hasNcea) {
                $this->defaultReport = self::NCEA_KEY;
            } else {
                $this->defaultReport = ['error' => 'Year 12/13 student not in NCEA or IB.'];
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
    private function navigationData(): void
    {
        $availableNavigation = [];

        /* Conditionally add NCEA option
         */
        if ($this->hasNcea()) {
            $this->hasNcea = true;
            $availableNavigation[self::NCEA_KEY] = 'NCEA';
        }

        /* Conditionally add IB option
         */
        if ($this->hasIb()) {
            $this->hasIb = true;
            $availableNavigation[self::IB_KEY] = 'IB';
        }

        /* Conditionally add School Grades option
         */
        if ($this->hasSchoolGrades()) {
            $this->hasSchoolGrades = true;
            $availableNavigation[self::SCHOOLGRADES_KEY] = 'School Grades';
        }

        /* Conditionally add Standardised Test option
         */
        if ((new ValidJuniorSchoolStudent())->isSatisfiedBy($this->user)) {
            $availableNavigation[self::STANDARDISEDTEST_KEY] = 'Standardised Test';
        }

        /* Conditionally add Comment option
         */
        if ($this->hasComments()) {
            $this->hasComments = true;
            $availableNavigation[self::COMMENTS_KEY] = 'Comments';
        }

        /* PDF Reports
         * This is a special case: it links to some other url that isn't in the Achievement Report
         */
        $availableNavigation['PDF Reports'] = 'PDF Reports';

        /* Conditionally add CoCurricular option
         */
        if ($this->hasCoCurricular()) {
            $this->hasCoCurricular = true;
            $availableNavigation[self::COCURRICULAR_KEY] = 'Co-Curricular';

        }

        $this->availableNavigation = $availableNavigation;
    }


    /**
     * Find out if they are in the Comments table
     */
    private function hasComments()
    {
        $query = "
            SELECT TOP 1 COUNT(*) AS 'computed'
            FROM dbo.uvSchoolBoxReportComments
            WHERE id = '{$this->externalUser['StudentID']}'
        ";

        $count = $this->db->getRow($query);

        return $count['computed'];
    }


    /**
     * Find out if they are in the School Grades table
     */
    private function hasSchoolGrades()
    {
        $query = "
            SELECT TOP 1 COUNT(*) AS 'computed'
            FROM dbo.uvSchoolBoxSchoolGradeResults
            WHERE ID = '{$this->externalUser['StudentID']}'
        ";

        $count = $this->db->getRow($query);

        return $count['computed'];
    }


    /**
     * Find out if they are in the NCEA table
     */
    private function hasNcea()
    {
        $query = "
            SELECT TOP 1 COUNT(*) AS 'computed'
            FROM dbo.uvSchoolBoxNCEAResults
            WHERE ID = '{$this->externalUser['StudentID']}'
        ";

        $count = $this->db->getRow($query);

        return $count['computed'];
    }


    /**
     * Find out if they are in the IB table
     */
    private function hasIb()
    {
        $query = "
            SELECT TOP 1 COUNT(*) AS 'computed'
            FROM dbo.uvSchoolBoxIBResults
            WHERE ID = '{$this->externalUser['StudentID']}'
        ";

        $count = $this->db->getRow($query);

        return $count['computed'];
    }


    /**
     * Find out if they have any data in Co Curricular
     *
     * @return int
     */
    private function hasCoCurricular(): int
    {
        $query = "
            SELECT TOP 1 COUNT(*) AS 'computed'
            FROM uvSchoolBoxCoCurricularClasses 
            WHERE StudentID = '{$this->externalUser['StudentID']}'
        ";

        $count = $this->db->getRow($query);

        return (int)$count['computed'];
    }


    /* Getters/Setters
     */

    /**
     * Get default report
     *
     * @return mixed
     */
    public function getDefaultReport()
    {
        return $this->defaultReport;
    }


    /**
     * Get available navigation
     *
     * @return array
     */
    public function getAvailableNavigation(): array
    {
        return $this->availableNavigation;
    }
}
