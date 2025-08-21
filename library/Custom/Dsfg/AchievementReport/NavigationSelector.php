<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Dsfg\AchievementReport;

use Alaress\Database\Connector\IConnector;
use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Generator;
use Schoolbox\User;

/**
 */
class NavigationSelector
{
    /**
     * Navigation keys
     */
    public const STANDARDISEDTEST_KEY = 'standardisedTest';
    public const YEAR11_KEY = 'year11';
    public const SCHOOLGRADES_KEY = 'schoolGrades';
    public const NCEA_KEY = 'ncea';
    public const IB_KEY = 'ib';
    public const COMMENTS_KEY = 'comments';
    public const COCURRICULAR_KEY = 'coCurricular';
    public const MYP_KEY = 'myp';
    public const DBVIEWTESTS_KEY = 'dbViewTests';

    /**
     * MSSQL db handler
     *
     * @var IConnector
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
     * @var int|null
     */
    private $year;


    /**
     * @var Generator
     */
    private $routeGenerator;


    /**
     * Available navigation options
     *
     * @var array
     */
    private $availableNavigation = [];


    /**
     * Default report information
     *
     * @var string|array|null
     */
    private $defaultReport;


    /**
     * NavigationSelector constructor.
     *
     * @param Generator  $routeGenerator
     * @param User       $user
     * @param array      $externalUser
     * @param IConnector $db
     * @param int|null   $year
     * @throws RouteNotFound
     */
    public function __construct(Generator $routeGenerator, User $user, array $externalUser, IConnector $db, ?int $year)
    {
        $this->routeGenerator = $routeGenerator;
        $this->user           = $user;
        $this->externalUser = $externalUser;
        $this->db = $db;
        $this->year = $year;

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

        /* Unknown Year Level gets a default report
         */
        if (null === $this->user->getYear()) {
            return;
        }

        /* Everyone else gets an evaluation based on the year string from lu_year_level
         */
        if ($this->user->getYear()->getName() == 11) {
            if ($this->hasYear11()) {
                $this->defaultReport = self::YEAR11_KEY;
            } else {
                $this->defaultReport = ['error' => 'Year 11 student has no data in the database view for Year 11.'];
            }
        } elseif (($this->user->getYear()->getName() >= 1) && ($this->user->getYear()->getName() <= 6)) {
            $this->defaultReport = self::STANDARDISEDTEST_KEY;
        } elseif ($this->hasMYP()) {
            $this->defaultReport = self::MYP_KEY;
        } elseif (($this->user->getYear()->getName() >= 9) && ($this->user->getYear()->getName() <= 10)) {
            $this->defaultReport = self::SCHOOLGRADES_KEY;
        } elseif (($this->user->getYear()->getName() >= 12) && ($this->user->getYear()->getName() <= 13)) {
            /* Need to decide whether this is an IB or an NCEA student
             */
            if ($this->hasIb()) {
                $this->defaultReport = self::IB_KEY;
            } elseif ($this->hasNcea()) {
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
     *
     * @return void
     * @throws RouteNotFound
     */
    private function navigationData(): void
    {
        $userYear = $this->user->getYear()?->getName();

        $availableNavigation = [];

        if ($userYear !== '11' && $this->hasMYP()) {
            $availableNavigation[self::MYP_KEY] = [
                'url' => $this->routeGenerator->generate('achievementreport.getMyp', [
                    'externalUserId' => $this->externalUser['StudentID'],
                ]),
                'label' => 'MYP',
            ];
        }

        /* Conditionally add NCEA option
         *
         * Note the NCEA report is not shown for Year 11 students because all
         * the NCEA data for Year 11 students is now included in the Year 11 tab.
         */
        if ($userYear !== '11' && $this->hasNcea()) {
            $availableNavigation[self::NCEA_KEY] = [
                'url' => $this->routeGenerator->generate('achievementreport.getNcea', [
                    'externalUserId' => $this->externalUser['StudentID'],
                ]),
                'label' => 'NCEA'
            ];
        }

        /* Conditionally add IB option
         */
        if ($this->hasIb()) {
            $availableNavigation[self::IB_KEY] = [
                'url' => $this->routeGenerator->generate('achievementreport.getIb', [
                    'externalUserId' => $this->externalUser['StudentID'],
                ]),
                'label' => 'IB'
            ];
        }

        /* Conditionally add Year 11 option
         */
        if ($userYear !== '11' && $this->hasYear11()) {
            $availableNavigation[self::YEAR11_KEY] = [
                'url' => $this->routeGenerator->generate('achievementreport.getYear11', [
                    'externalUserId' => $this->externalUser['StudentID'],
                ]),
                'label' => 'Year 11'
            ];
        }

        /* Conditionally add School Grades option
         */
        if ($this->hasSchoolGrades()) {
            $availableNavigation[self::SCHOOLGRADES_KEY] = [
                'url' => $this->routeGenerator->generate('achievementreport.getSchoolGrades', [
                    'externalUserId' => $this->externalUser['StudentID'],
                ]),
                'label' => 'School Grades'
            ];
        }

        if ($userYear === '11' && $this->hasMYP()) {
            $availableNavigation[self::MYP_KEY] = [
                'url' => $this->routeGenerator->generate('achievementreport.getMyp', [
                    'externalUserId' => $this->externalUser['StudentID'],
                ]),
                'label' => 'MYP',
            ];
        }

        if (isset($this->externalUser['StudentID'])) {
            /* Conditionally add Standardised Test option
             */
            $availableNavigation[self::STANDARDISEDTEST_KEY] = [
                'url' => $this->routeGenerator->generate('achievementreport.getStandardisedTests', [
                    'externalUserId' => $this->externalUser['StudentID'],
                ]),
                'label' => 'Standardised Tests'
            ];
        }

        /* Conditionally add Comment option
         */
        if ($this->hasComments()) {
            $availableNavigation[self::COMMENTS_KEY] = [
                'url' => $this->routeGenerator->generate('achievementreport.getComments', [
                    'externalUserId' => $this->externalUser['StudentID'],
                ]),
                'label' => 'Comments'
            ];
        }

        /* PDF Reports
         * This is a special case: it links to some other url that isn't in the Achievement Report
         */
        $availableNavigation['PDF Reports'] = [
            'url' => "/user/profile/documents/reports/{$this->user->getId()}",
            'label' => 'PDF Reports'
        ];

        /* Conditionally add CoCurricular option
         */
        if ($this->hasCoCurricular()) {
            $availableNavigation[self::COCURRICULAR_KEY] = [
                'url' => $this->routeGenerator->generate('achievementreport.getCoCurricular', [
                    'externalUserId' => $this->externalUser['StudentID'],
                ]),
                'label' => 'Co-Curricular'
            ];
        }

        if (in_array($_SESSION['username'], ['alaress', 'psingh', 'swalker'])) {
            $availableNavigation[self::DBVIEWTESTS_KEY] = [
                'url' => $this->routeGenerator->generate('achievementreport.getDbViewTests', [
                    'externalUserId' => $this->externalUser['StudentID'],
                ]),
                'label' => 'DB View Tests'
            ];
        }

        $this->availableNavigation = $availableNavigation;
    }


    /**
     * Find out if they are in the Comments table
     *
     * @return bool
     */
    private function hasComments(): bool
    {
        if (!isset($this->externalUser['StudentID'])) {
            return false;
        }

        $params = [
            'externalId' => $this->externalUser['StudentID'],
        ];

        $query = "
            SELECT TOP 1 COUNT(*) AS 'computed'
            FROM dbo.uvSchoolBoxReportComments
            WHERE id = :externalId
        ";

        return (bool)$this->db->getOne($query, $params);
    }


    /**
     * Find out if they are in the School Grades table
     *
     * @return bool
     */
    private function hasSchoolGrades(): bool
    {
        if (!isset($this->externalUser['StudentID'])) {
            return false;
        }

        $params = [
            'externalId' => $this->externalUser['StudentID'],
        ];

        $query = "
            SELECT TOP 1 COUNT(*) AS 'computed'
            FROM dbo.uvSchoolBoxSchoolGradeResults
            WHERE ID = :externalId
        ";

        return (bool)$this->db->getOne($query, $params);
    }


    /**
     * Find out if they are in the NCEA table
     *
     * @return bool
     */
    private function hasNcea(): bool
    {
        if (!isset($this->externalUser['StudentID'])) {
            return false;
        }

        $params = [
            'externalId' => $this->externalUser['StudentID'],
        ];

        $query = "
            SELECT TOP 1 COUNT(*) AS 'computed'
            FROM " . NCEA\Base::MAIN_DB_TABLE_NCEA . "
            WHERE ID = :externalId
        ";

        return (bool)$this->db->getOne($query, $params);
    }


    /**
     * Find out if they are in the NCEA table
     *
     * @return bool
     */
    private function hasYear11(): bool
    {
        if (!isset($this->externalUser['StudentID'])) {
            return false;
        }

        $params = [
            'externalId' => $this->externalUser['StudentID'],
        ];

        $query = "
            SELECT TOP 1 COUNT(*) AS 'computed'
            FROM " . NCEA\Base::MAIN_DB_TABLE_YEAR11 . "
            WHERE ID = :externalId
        ";

        try {
            $hasYear11 = (bool)$this->db->getOne($query, $params);
        } catch (\Exception) {
            $hasYear11 = false;
        }

        return $hasYear11;
    }


    /**
     * Find out if they are in the IB table
     *
     * @return bool
     */
    private function hasIb(): bool
    {
        if (!isset($this->externalUser['StudentID'])) {
            return false;
        }

        $params = [
            'externalId' => $this->externalUser['StudentID'],
        ];

        $query = "
            SELECT TOP 1 COUNT(*) AS 'computed'
            FROM dbo.uvSchoolBoxIBResults
            WHERE ID = :externalId
        ";

        return (bool)$this->db->getOne($query, $params);
    }


    /**
     * Find out if they have any data in Co Curricular
     *
     * @return bool
     */
    private function hasCoCurricular(): bool
    {
        if (!isset($this->externalUser['StudentID'])) {
            return false;
        }

        $params = [
            'externalId' => $this->externalUser['StudentID'],
        ];

        $query = "
            SELECT TOP 1 COUNT(*) AS 'computed'
            FROM dbo.uvSchoolBoxCoCurricularClasses
            WHERE StudentID = :externalId
        ";

        return (bool)$this->db->getOne($query, $params);
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

    /**
     * @return bool
     */
    private function hasMYP(): bool
    {
        if (!isset($this->externalUser['StudentID'])) {
            return false;
        }

        $params = [
            'externalId' => $this->externalUser['StudentID'],
        ];

        $query = "
            SELECT TOP 1 COUNT(*) AS 'computed'
            FROM dbo.uvSchoolBoxMYPResults_CurrentOverview
            WHERE ID = :externalId
        ";

        return (bool)$this->db->getOne($query, $params);
    }
}
