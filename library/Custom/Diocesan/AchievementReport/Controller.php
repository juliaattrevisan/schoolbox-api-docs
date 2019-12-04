<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Custom\Diocesan\AchievementReport;

use Alaress\Web\Controller\Base;
use Custom\Diocesan\AchievementReport\Comments;
use Custom\Diocesan\AchievementReport\Comments\ValidJuniorSchoolStudent;
use Custom\Diocesan\AchievementReport\StandardisedTest\Navigation;
use Custom\Diocesan\AchievementReport\StandardisedTest\PATHeading;
use Custom\Diocesan\AchievementReport\StandardisedTest\PATNavigation;
use Custom\Diocesan\AchievementReport\StandardisedTest\PAT;
use MSSQL;
use Schoolbox\User;

/**
 * Class Controller
 *
 * @package Custom\Diocesan\AchievementReport
 */
class Controller extends Base {

    /**
     * Internal/External types
     *
     * Maps to css classes
     *
     * @var array
     */
    private const TYPES = [
        'IN' => [
            'long' => 'Internally Assessed',
            'short' => 'Internal',
            'css' => 'type-pink',
        ],
        'EX' => [
            'long' => 'Externally Assessed',
            'short' => 'External',
            'css' => 'type-blue'
        ]
    ];

    /**
     * Selects which report to redirect towards, based on certain
     * conditions particular to a student
     *
     * @param MSSQL    $db
     * @param User     $user
     * @param array    $externalUser
     * @param int|null $year
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getSelector(MSSQL $db, User $user, array $externalUser, ?int $year)
    {
        $selector = new NavigationSelector($this->getRouter(), $user, $externalUser, $db, $year);
        $selector->determineDefaultReport();

        $report = $selector->getDefaultReport();

        /* Redirect based on default report selection
         *
         * Needs to happen so that the /ib, /schoolGrades and /ncea
         * prefixes exist, otherwise you end up at 404 from the
         * default report when you open a year div
         */
        switch ($report) {
            case NavigationSelector::SCHOOLGRADES_KEY:
                $this->getResponse()->redirect->to(
                    $this->getRouter()->generate('achievementreport.getSchoolGrades', [
                        'externalUserId' => $externalUser['StudentID'],
                        'year' => $year
                    ])
                );

                break;

            case NavigationSelector::IB_KEY:
                $this->getResponse()->redirect->to(
                    $this->getRouter()->generate('achievementreport.getIb', [
                        'externalUserId' => $externalUser['StudentID'],
                        'year' => $year
                    ])
                );

                break;

            case NavigationSelector::NCEA_KEY:
                $this->getResponse()->redirect->to(
                    $this->getRouter()->generate('achievementreport.getNcea', [
                        'externalUserId' => $externalUser['StudentID'],
                        'year' => $year
                    ])
                );

                break;

            case NavigationSelector::COMMENTS_KEY:
                $this->getResponse()->redirect->to(
                    $this->getRouter()->generate('achievementreport.getComments', [
                        'externalUserId' => $externalUser['StudentID'],
                        'year' => $year
                    ])
                );

                break;

            case NavigationSelector::STANDARDISEDTEST_KEY:
                $this->getResponse()->redirect->to(
                    $this->getRouter()->generate('achievementreport.getStandardisedTest', [
                        'externalUserId' => $externalUser['StudentID'],
                        'year' => $year
                    ])
                );

                break;

            case NavigationSelector::COCURRICULAR_KEY:
                $this->getResponse()->redirect->to(
                    $this->getRouter()->generate('achievementreport.getCoCurricular', [
                        'externalUserId' => $externalUser['StudentID'],
                        'year' => $year
                    ])
                );

                break;

            /* Error state - render an error page and leave it
             * at that, I guess
             */
            default:
                $this->getResponse()->content->set(array(
                    'template' => '@templates/Error.twig',
                    'data' => array(
                        'errorMessage' => $report['error']
                    )
                ));

                break;
        }
    }


    /**
     * Standardised Test placeholder
     *
     * @param MSSQL    $db
     * @param User     $user
     * @param array    $externalUser
     * @param int|null $year
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getStandardisedTest(MSSQL $db, User $user, array $externalUser, ?int $year)
    {

        /* Default to this year if none specified
         */
        $selectedYear = $year ?? (int)date('Y');

        /* Each graph's payload and visibility status
         */
        $tablePayload = new PAT\Table\Data($user, $externalUser, $db, $year);

        $PATTable = [
            'data' => $tablePayload->findData(),
            'visible' => (new PAT\Table\ValidHasData())->isSatisfiedBy($user->getYear(), $tablePayload->hasData())
        ];

        $mathematicsPayload = (new PAT\Mathematics\Data($user, $externalUser, $db, $selectedYear))->findData();

        $PATMathematics = [
            'data' => $mathematicsPayload->getData(),
            'visible' => (new PAT\Mathematics\ValidHasData())->isSatisfiedBy($user->getYear(), $mathematicsPayload->hasData())
        ];

        $listeningComprehensionPayload = (new PAT\ListeningComprehension\Data($user, $externalUser, $db, $selectedYear))->findData();

        $PATListeningComprehension = [
            'data' => $listeningComprehensionPayload->getData(),
            'visible' => (new PAT\ListeningComprehension\ValidHasData())->isSatisfiedBy($user->getYear(), $listeningComprehensionPayload->hasData())
        ];

        $readingVocabularyPayload = (new PAT\ReadingVocabulary\Data($user, $externalUser, $db, $selectedYear))->findData();

        $PATReadingVocabulary = [
            'data' => $readingVocabularyPayload->getData(),
            'visible' => (new PAT\ReadingVocabulary\ValidHasData())->isSatisfiedBy($user->getYear(), $readingVocabularyPayload->hasData())
        ];

        $readingComprehensionPayload = (new PAT\ReadingComprehension\Data($user, $externalUser, $db, $selectedYear))->findData();

        $PATReadingComprehension = [
            'data' => $readingComprehensionPayload->getData(),
            'visible' => (new PAT\ReadingComprehension\ValidHasData())->isSatisfiedBy($user->getYear(), $readingComprehensionPayload->hasData())
        ];

        /* PAT Graph heading is only visible when there's at least one graph to show
         */
        $headingVisible = $PATMathematics['visible'] || $PATListeningComprehension['visible'] || $PATReadingVocabulary['visible'] || $PATReadingComprehension['visible'];

        $this->getResponse()->content->set(array(
            'template' => '@standardisedTest/index.twig',
            'data' => [
                'navigation' => array(
                    'all' => (new NavigationSelector($this->getRouter(), $user, $externalUser, $db, $year))->getAvailableNavigation(),
                    'selected' => NavigationSelector::STANDARDISEDTEST_KEY
                ),
                'externalUser' => $externalUser,
                'user' => $user,
                'year' => $year,

                'subnavigation' => [
                    'all' => (new Navigation($this->getRouter(), $user, $externalUser, $db, $year))->getAvailableNavigation(),

                    /* TODO: For each Standardised Test report piece
                     */
                    'selected' => NavigationSelector::STANDARDISEDTEST_KEY
                ],

                'PATTable' => $PATTable,
                'PATNavigation' => [
                    'all' => (new PATNavigation($user, $externalUser, $db, $year))->findData(),
                    'selected' => $year ?? (int)date('Y')
                ],
                'PATHeading' => [
                    'label' => (new PATHeading($user, $externalUser, $db, $year))->findData(),
                    'visible' => $headingVisible
                ],
                'PATMathematics' => $PATMathematics,
                'PATListeningComprehension' => $PATListeningComprehension,
                'PATReadingVocabulary' => $PATReadingVocabulary,
                'PATReadingComprehension' => $PATReadingComprehension
            ]
        ));
    }


    /**
     * School Grades page
     *
     * @param MSSQL    $db
     * @param User     $user
     * @param array    $externalUser
     * @param int|null $year
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getSchoolGrades(MSSQL $db, User $user, array $externalUser, ?int $year)
    {
        $footer = new SchoolGrades\Footer($user, $externalUser, $db, null);

        $gradeSummaryTable = new SchoolGrades\GradeSummary($user, $externalUser, $db, $year);
        $yearlyBreakdownTable = new SchoolGrades\YearlyBreakdown($user, $externalUser, $db, $year);
        $entriesAndResultsTable = new SchoolGrades\EntriesAndResults($user, $externalUser, $db, $year);

        $this->getResponse()->content->set(array(
            'template' => '@schoolGrades/index.twig',
            'data' => array(
                'navigation' => array(
                    'all' => (new NavigationSelector($this->getRouter(), $user, $externalUser, $db, $year))->getAvailableNavigation(),
                    'selected' => NavigationSelector::SCHOOLGRADES_KEY
                ),
                'footer' => array(
                    'data' => $footer->findData(),
                ),
                'preselectedYear' => $year,
                'externalUser' => $externalUser,
                'user' => $user,

                'gradeSummary' => array(
                    'heading' => $gradeSummaryTable->getHeading(),
                    'data' => $gradeSummaryTable->findData(),
                    'columns' => array_merge($gradeSummaryTable->getLetterRegistry(), array('availableGrades'))
                ),

                'yearlyBreakdown' => array(
                    'heading' => $yearlyBreakdownTable->getHeading(),
                    'data' => $yearlyBreakdownTable->findData(),
                ),

                'entriesAndResults' => array(
                    'heading' => $entriesAndResultsTable->getHeading(),
                    'data' => $entriesAndResultsTable->findData(),
                    'currentYear' => $entriesAndResultsTable->getCurrentYear()
                )
            )
        ));
    }


    /**
     * IB page
     *
     * @param MSSQL    $db
     * @param User     $user
     * @param array    $externalUser
     * @param int|null $year
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getIb(MSSQL $db, User $user, array $externalUser, ?int $year)
    {
        /* The selected year should contain data. If a selected
         * year contains no data, probably 404 or something - you
         * could fiddle with the url to get illegal values
         */
        $allYears = (new IB\Navigation($user, $externalUser, $db, $year))->findData();

        /* If $year wasn't provided, go with the most recent year we have
         */
        if (null === $year && \count($allYears)) {
            $this->getResponse()->redirect->to(
                $this->getRouter()->generate('achievementreport.getIb', [
                    'externalUserId' => $externalUser['StudentID'],
                    'year' => $allYears[0]
                ])
            );
        }

        $this->getResponse()->content->set([
            'template' => '@ib/index.twig',
            'data' => [
                'navigation' => [
                    'all' => (new NavigationSelector($this->getRouter(), $user, $externalUser, $db, $year))->getAvailableNavigation(),
                    'selected' => NavigationSelector::IB_KEY
                ],

                'preselectedYear' => $year,
                'externalUser' => $externalUser,
                'user' => $user,
                'year' => $year,

                'yearlyBreakdown' => [
                    'navigation' => [
                        'all' => $allYears,
                        'selected' => $year
                    ],
                    'terms' => IB\YearlyBreakdown::INITIAL_TERMS,
                    'data' => (new IB\YearlyBreakdown($user, $externalUser, $db, $year))->findData(),
                ],

                'footer' => (new IB\Footer($user, $externalUser, $db, $year))->findData()
            ]
        ]);
    }


    /**
     * NCEA page
     *
     * @param MSSQL    $db
     * @param User     $user
     * @param array    $externalUser
     * @param int|null $year
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getNcea(MSSQL $db, User $user, array $externalUser, ?int $year)
    {
        $footer = new NCEA\Footer($user, $externalUser, $db, null);

        $literacyNumeracyCheckbox = new NCEA\LiteracyNumeracyCheckbox($user, $externalUser, $db, $year);
        $qualifications = new NCEA\Qualifications($user, $externalUser, $db, null);
        $creditSummary = new NCEA\CreditSummary($user, $externalUser, $db, null);

        $output = array(
            'template' => '@ncea/index.twig',
            'data' => array(
                'navigation' => array(
                    'all' => (new NavigationSelector($this->getRouter(), $user, $externalUser, $db, $year))->getAvailableNavigation(),
                    'selected' => NavigationSelector::NCEA_KEY
                ),
                'footer' => array(
                    'data' => $footer->findData(),
                ),
                'preselectedYear' => $year,
                'externalUser' => $externalUser,
                'user' => $user,
                'year' => $year,

                /* That checkbox thing at the top
                 */
                'literacyNumeracyCheckbox' => array(
                    'heading' => $literacyNumeracyCheckbox->getHeading(),
                    'data' => $literacyNumeracyCheckbox->findData()
                ),

                /* Qualifications rating
                 */
                'qualifications' => array(
                    'heading' => $qualifications->getHeading(),
                    'data' => $qualifications->findData(),
                ),

                /* Credit Summary graphs for all years combined
                 */
                'creditSummary' => array(
                    'heading' => $creditSummary->getHeading(),
                    'data' => $creditSummary->findData(),
                    'allLetters' => $creditSummary->getLetterRegistry(),
                    'allTypes' => self::TYPES,

                    /* Suffix for graph div id's
                     */
                    'suffix' => 'all',
                ),
            )
        );

        /* The selected year should contain data. If a selected
         * year contains no data, probably 404 or something - you
         * could fiddle with the url to get illegal values
         */
        $navigation = new NCEA\CourseCreditSummaryNavigation($user, $externalUser, $db, null);
        $allYears = $navigation->findData();

        if (\count($allYears)) {

            /* Course Credit Summary year selectors
             */
            $output['data']['courseCreditSummaryNavigation'] = array(
                'heading' => $navigation->getHeading(),
                'all' => $allYears,
                'selected' => $year
            );

            /* Conditionally, if a year was selected; do another round of
             * processing specific to that year. Otherwise skip all this
             * shenanigans altogether
             */
            if ($year) {
                $creditSummaryYear = new NCEA\CreditSummary($user, $externalUser, $db, $year);
                $courseCreditSummary = new NCEA\CourseCreditSummary($user, $externalUser, $db, $year);
                $entriesAndResults = new NCEA\EntriesAndResults($user, $externalUser, $db, $year);

                /* Course Credit Summary graphs for a specific year
                 */
                $output['data']['creditSummaryYear'] = array(
                    'data' => $creditSummaryYear->findData(),
                    'allLetters' => $creditSummaryYear->getLetterRegistry(),

                    /* Suffix for graph div id's
                     */
                    'suffix' => "year{$year}",
                );

                /* Course Credit Summary for a specific year
                 */
                $output['data']['courseCreditSummary'] = array(
                    'data' => $courseCreditSummary->findData(),
                    'allLetters' => $courseCreditSummary->getLetterRegistry(),
                );

                /* Entries And Results for a specific year
                 */

                /* Get all the comments for $year for this user
                 * Then rearrange all the subjects
                 */
                $output['data']['entriesAndResults'] = array(
                    'data' => $entriesAndResults->findData(),
                    'comments' => (new Comments\Comments($user, $externalUser, $db, $year))->findNCEAData(),
                    'allLetters' => $entriesAndResults->getLetterRegistry(),
                    'allTypes' => self::TYPES,
                    'year' => $year,
                    'currentYear' => $entriesAndResults->getCurrentYear()
                );
            }
        }

        $this->getResponse()->content->set($output);
    }


    /**
     * Get all the comments for $externalUser
     *
     * @param MSSQL    $db
     * @param User     $user
     * @param array    $externalUser
     * @param int|null $year
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getComments(MSSQL $db, User $user, array $externalUser, ?int $year)
    {
        /* The selected year should contain data. If a selected
         * year contains no data, probably 404 or something - you
         * could fiddle with the url to get illegal values
         */
        $allYears = (new Comments\CommentsNavigation($user, $externalUser, $db, $year))->findData();

        /* If $year wasn't provided, go with the most recent year we have
         */
        if (null === $year && \count($allYears)) {
            $this->getResponse()->redirect->to(
                $this->getRouter()->generate('achievementreport.getComments', [
                    'externalUserId' => $externalUser['StudentID'],
                    'year' => $allYears[0]
                ])
            );
        }

        $output = [
            'template' => '@comments/index.twig',
            'data' => [
                'navigation' => [
                    'all' => (new NavigationSelector($this->getRouter(), $user, $externalUser, $db, $year))->getAvailableNavigation(),
                    'selected' => NavigationSelector::COMMENTS_KEY
                ],
                'externalUser' => $externalUser,
                'user' => $user,
                'year' => $year,
                'terms' => Comments\Comments::INITIAL_TERMS,
                'isJuniorSchoolStudent' => (new ValidJuniorSchoolStudent())->isSatisfiedBy($user),
                'commentsNavigation' => [
                    'all' => $allYears,
                    'selected' => $year
                ],
                'comments' => (new Comments\Comments($user, $externalUser, $db, $year))->findData()
            ]
        ];

        $this->getResponse()->content->set($output);
    }


    /**
     * Get all the cocurricular activities for $externalUser
     *
     * @param MSSQL    $db
     * @param User     $user
     * @param array    $externalUser
     * @param int|null $year
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getCoCurricular(MSSQL $db, User $user, array $externalUser, ?int $year)
    {
        $output = [
            'template' => '@coCurricular/index.twig',
            'data' => [
                'navigation' => [
                    'all' => (new NavigationSelector($this->getRouter(), $user, $externalUser, $db, $year))->getAvailableNavigation(),
                    'selected' => NavigationSelector::COCURRICULAR_KEY
                ],
                'externalUser' => $externalUser,
                'user' => $user,
                'year' => $year,
                'activities' => (new CoCurricular\Activities($user, $externalUser, $db, $year))->findData(),
            ]
        ];

        $this->getResponse()->content->set($output);
    }
}
