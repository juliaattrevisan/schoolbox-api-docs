<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest;

use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Router;
use MSSQL;
use Schoolbox\User;

/**
 * Class Navigation
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest
 */
class Navigation
{

    /**
     * Consts for each of the Standardised Test pieces
     */
    public const STANDARDISEDTEST_PAT_KEY = 'pat';
    public const STANDARDISEDTEST_STAR_KEY = 'star';
    public const STANDARDISEDTEST_READING_KEY = 'reading';
    public const STANDARDISEDTEST_WRITING_KEY = 'writing';
    public const STANDARDISEDTEST_MATHEMATICS_KEY = 'mathematics';
    public const STANDARDISEDTEST_LITERACYNUMERACY_KEY = 'literacyNumeracy';


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
     * @var int|null
     */
    private $year;


    /**
     * @var Router
     */
    private $router;


    /**
     * Available navigation options
     *
     * @var array
     */
    private $availableNavigation = [];


    /**
     * NavigationSelector constructor.
     *
     * @param Router   $router
     * @param User     $user
     * @param array    $externalUser
     * @param MSSQL    $db
     * @param int|null $year
     * @throws RouteNotFound
     */
    public function __construct(Router $router, User $user, array $externalUser, MSSQL $db, ?int $year)
    {
        $this->router = $router;
        $this->user = $user;
        $this->externalUser = $externalUser;
        $this->db = $db;
        $this->year = $year;

        /* Determine navigation options
         */
        $this->navigationData();
    }


    /**
     * Returns data for twig rendering
     *
     * Finds all the years that have data
     *
     * @return void
     * @throws RouteNotFound
     */
    private function navigationData(): void
    {
        $availableNavigation = [];

        /* Conditional PAT report
         */
        if ((new PAT\ValidPAT($this->db))->isSatisifiedBy($this->user, $this->externalUser)) {
            $availableNavigation[self::STANDARDISEDTEST_PAT_KEY] = [
                'url' => $this->router->generate('achievementreport.getStandardisedTestPAT', [
                    'externalUserId' => $this->externalUser['StudentID'],
                    'year' => $this->year
                ]),
                'label' => 'PAT'
            ];
        }

        /* Conditional STAR report
         */
        $availableNavigation[self::STANDARDISEDTEST_STAR_KEY] = [
            'url' => $this->router->generate('achievementreport.getStandardisedTestSTAR', [
                'externalUserId' => $this->externalUser['StudentID'],
                'year' => $this->year
            ]),
            'label' => 'STAR'
        ];

        /* Conditional Reading report
         */
        $availableNavigation[self::STANDARDISEDTEST_READING_KEY] = [
            'url' => $this->router->generate('achievementreport.getStandardisedTestReading', [
                'externalUserId' => $this->externalUser['StudentID'],
                'year' => $this->year
            ]),
            'label' => 'Reading'
        ];

        /* Conditional Writing report
         */
        $availableNavigation[self::STANDARDISEDTEST_WRITING_KEY] = [
            'url' => $this->router->generate('achievementreport.getStandardisedTestWriting', [
                'externalUserId' => $this->externalUser['StudentID'],
                'year' => $this->year
            ]),
            'label' => 'Writing'
        ];

        /* Conditional Mathematics report
         */
        $availableNavigation[self::STANDARDISEDTEST_MATHEMATICS_KEY] = [
            'url' => $this->router->generate('achievementreport.getStandardisedTestMathematics', [
                'externalUserId' => $this->externalUser['StudentID'],
                'year' => $this->year
            ]),
            'label' => 'Mathematics'
        ];

        /* Conditional Literacy/Numeracy report
         */
        $availableNavigation[self::STANDARDISEDTEST_LITERACYNUMERACY_KEY] = [
            'url' => '',
            'label' => 'Literacy/Numeracy'
        ];

        $this->availableNavigation = $availableNavigation;
    }


    /* Getters/Setters
     */


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
