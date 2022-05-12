<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Dsfg\AchievementReport;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Custom\Dsfg\AchievementReport\Comments;
use Custom\Dsfg\AchievementReport\Comments\ValidJuniorSchoolStudent;
use Custom\Dsfg\AchievementReport\StandardisedTest;
use Custom\Dsfg\AchievementReport\StandardisedTest\PAT;
use Custom\Dsfg\AchievementReport\StandardisedTest\STAR;
use Custom\Dsfg\AchievementReport\StandardisedTest\Reading;
use Custom\Dsfg\AchievementReport\StandardisedTest\Writing;
use Custom\Dsfg\AchievementReport\StandardisedTest\Mathematics;
use Custom\Dsfg\AchievementReport\StandardisedTest\LiteracyNumeracy;
use Schoolbox\User;

/**
 */
class Controller extends Base
{

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
     * @param IConnector $db
     * @param User       $user
     * @param array      $externalUser
     * @param int|null   $year
     * @throws RouteNotFound
     */
    public function getSelector(IConnector $db, User $user, array $externalUser, ?int $year): void
    {
        /* Check whether the Achievement Reports are enabled
         */
        if (
            !(new ValidAccess())->isSatisfiedBy($this->getConfigLoader()->get('dio_achievement_report_enabled'), $this->getUser())
            || !isset($externalUser['StudentID'])
        ) {
            $this->getResponse()->content->set([
                'template' => '@achievementreport/unavailable.twig',
                'data' => [
                    'message' => $this->getConfigLoader()->get('dio_achievement_report_unavailable')
                ]
            ]);

            return;
        }

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
                    $this->getRouter()->generate('achievementreport.getStandardisedTests', [
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

            case NavigationSelector::MYP_KEY:
                $this->getResponse()->redirect->to(
                    $this->getRouter()->generate('achievementreport.getMyp', [
                        'externalUserId' => $externalUser['StudentID'],
                        'year'           => $year,
                    ])
                );

                break;

            /* Error state - render an error page and leave it
             * at that, I guess
             */
            default:
                $this->getResponse()->content->set([
                    'template' => '@achievementreport/Error.twig',
                    'data' => [
                        'errorMessage' => $report['error']
                    ]
                ]);

                break;
        }
    }


    /**
     * Standardised Tests index
     *
     * @param IConnector $db
     * @param User       $user
     * @param array      $externalUser
     * @param int|null   $year
     * @throws RouteNotFound
     */
    public function getStandardisedTests(IConnector $db, User $user, array $externalUser, ?int $year): void
    {

        /* TODO: Redirect to the first available section of the standardised test
         * PAT, STAR, Reading, Writing, Mathematics, Literacy/Numeracy
         */

        $this->getResponse()->redirect->to(
            $this->getRouter()->generate('achievementreport.getStandardisedTestsPAT', [
                'externalUserId' => $externalUser['StudentID'],
                'year' => $year
            ])
        );
    }


    /**
     * PAT section of Standardised Test
     *
     * @param IConnector $db
     * @param User       $user
     * @param array      $externalUser
     * @param int|null   $year
     * @throws RouteNotFound
     */
    public function getStandardisedTestsPAT(IConnector $db, User $user, array $externalUser, ?int $year): void
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

        $this->getResponse()->content->set([
            'template' => '@standardisedTest/PAT/index.twig',
            'data' => [
                'navigation' => [
                    'all' => (new NavigationSelector($this->getRouter(), $user, $externalUser, $db, $year))->getAvailableNavigation(),
                    'selected' => NavigationSelector::STANDARDISEDTEST_KEY
                ],
                'externalUser' => $externalUser,
                'user' => $user,
                'year' => $year,

                'subnavigation' => [
                    'all' => (new StandardisedTest\Navigation($this->getRouter(), $user, $externalUser, $db, $year))->getAvailableNavigation(),
                    'selected' => StandardisedTest\Navigation::STANDARDISEDTEST_PAT_KEY
                ],

                'PATTable' => $PATTable,
                'PATNavigation' => [
                    'all' => (new PAT\Navigation($user, $externalUser, $db, $selectedYear))->findData(),
                    'selected' => $selectedYear
                ],
                'PATHeading' => [
                    'label' => (new PAT\Heading($user, $externalUser, $db, $selectedYear))->findData(),
                    'visible' => $headingVisible
                ],
                'PATMathematics' => $PATMathematics,
                'PATListeningComprehension' => $PATListeningComprehension,
                'PATReadingVocabulary' => $PATReadingVocabulary,
                'PATReadingComprehension' => $PATReadingComprehension
            ]
        ]);
    }


    /**
     * @param IConnector $db
     * @param User       $user
     * @param array      $externalUser
     * @param int|null   $year
     * @throws RouteNotFound
     */
    public function getStandardisedTestsSTAR(IConnector $db, User $user, array $externalUser, ?int $year): void
    {
        /* Default to this year if none specified
         */
        $selectedYear = $year ?? (int)date('Y');

        /* Each graph's payload and visibility status
         */
        $tablePayload = new STAR\Table\Data($user, $externalUser, $db, $year);

        $STARTable = [
            'data' => $tablePayload->findData(),
            'visible' => (new STAR\Table\ValidHasData())->isSatisfiedBy($user->getYear(), $tablePayload->hasData())
        ];

        /* STAR Stanine
         */
        $STARStaninePayload = (new STAR\Stanine\Data($user, $externalUser, $db, $selectedYear))->findData();

        $STARStanine = [
            'data' => $STARStaninePayload->getData(),
            'visible' => (new STAR\Stanine\ValidHasData())->isSatisfiedBy($user->getYear(), $STARStaninePayload->hasData())
        ];

        $this->getResponse()->content->set([
            'template' => '@standardisedTest/STAR/index.twig',
            'data' => [
                'navigation' => [
                    'all' => (new NavigationSelector($this->getRouter(), $user, $externalUser, $db, $year))->getAvailableNavigation(),
                    'selected' => NavigationSelector::STANDARDISEDTEST_KEY
                ],
                'externalUser' => $externalUser,
                'user' => $user,
                'year' => $year,

                'subnavigation' => [
                    'all' => (new StandardisedTest\Navigation($this->getRouter(), $user, $externalUser, $db, $year))->getAvailableNavigation(),
                    'selected' => StandardisedTest\Navigation::STANDARDISEDTEST_STAR_KEY
                ],

                'STARTable' => $STARTable,
                'STARStanine' => $STARStanine,
                'STARStanineNavigation' => [
                    'all' => (new STAR\Stanine\Navigation($user, $externalUser, $db, $selectedYear))->findData(),
                    'selected' => $selectedYear
                ],
            ]
        ]);
    }


    /**
     * @param IConnector $db
     * @param User       $user
     * @param array      $externalUser
     * @param int|null   $year
     * @throws RouteNotFound
     */
    public function getStandardisedTestsReading(IConnector $db, User $user, array $externalUser, ?int $year): void
    {
        /* Default to this year if none specified
         */
        $selectedYear = $year ?? (int)date('Y');

        /* Reading
         */
        $readingPayload = (new Reading\Data($user, $externalUser, $db, $selectedYear));

        $reading = [
            'data' => $readingPayload->findData(),
            'visible' => (new Reading\ValidHasData())->isSatisfiedBy($user->getYear(), $readingPayload->hasData())
        ];

        $this->getResponse()->content->set([
            'template' => '@standardisedTest/Reading/index.twig',
            'data' => [
                'navigation' => [
                    'all' => (new NavigationSelector($this->getRouter(), $user, $externalUser, $db, $year))->getAvailableNavigation(),
                    'selected' => NavigationSelector::STANDARDISEDTEST_KEY
                ],
                'externalUser' => $externalUser,
                'user' => $user,
                'year' => $year,

                'subnavigation' => [
                    'all' => (new StandardisedTest\Navigation($this->getRouter(), $user, $externalUser, $db, $year))->getAvailableNavigation(),
                    'selected' => StandardisedTest\Navigation::STANDARDISEDTEST_READING_KEY
                ],

                'reading' => $reading
            ]
        ]);
    }


    /**
     * @param IConnector $db
     * @param User       $user
     * @param array      $externalUser
     * @param int|null   $year
     * @throws RouteNotFound
     */
    public function getStandardisedTestsWriting(IConnector $db, User $user, array $externalUser, ?int $year): void
    {
        /* Default to this year if none specified
         */
        $selectedYear = $year ?? (int)date('Y');

        /* Writing
         */
        $writingPayload = (new Writing\Data($user, $externalUser, $db, $selectedYear));

        $writing = [
            'data' => $writingPayload->findData(),
            'visible' => (new Writing\ValidHasData())->isSatisfiedBy($user->getYear(), $writingPayload->hasData())
        ];

        $this->getResponse()->content->set([
            'template' => '@standardisedTest/Writing/index.twig',
            'data' => [
                'navigation' => [
                    'all' => (new NavigationSelector($this->getRouter(), $user, $externalUser, $db, $year))->getAvailableNavigation(),
                    'selected' => NavigationSelector::STANDARDISEDTEST_KEY
                ],
                'externalUser' => $externalUser,
                'user' => $user,
                'year' => $year,

                'subnavigation' => [
                    'all' => (new StandardisedTest\Navigation($this->getRouter(), $user, $externalUser, $db, $year))->getAvailableNavigation(),
                    'selected' => StandardisedTest\Navigation::STANDARDISEDTEST_WRITING_KEY
                ],

                'writing' => $writing
            ]
        ]);
    }


    /**
     * @param IConnector $db
     * @param User       $user
     * @param array      $externalUser
     * @param int|null   $year
     * @throws RouteNotFound
     */
    public function getStandardisedTestsMathematics(IConnector $db, User $user, array $externalUser, ?int $year): void
    {
        /* Default to this year if none specified
         */
        $selectedYear = $year ?? (int)date('Y');

        /* Mathematics
         */
        $mathematicsPayload = (new Mathematics\Data($user, $externalUser, $db, $selectedYear));

        $mathematics = [
            'data' => $mathematicsPayload->findData(),
            'visible' => (new Mathematics\ValidHasData())->isSatisfiedBy($user->getYear(), $mathematicsPayload->hasData())
        ];

        $this->getResponse()->content->set([
            'template' => '@standardisedTest/Mathematics/index.twig',
            'data' => [
                'navigation' => [
                    'all' => (new NavigationSelector($this->getRouter(), $user, $externalUser, $db, $year))->getAvailableNavigation(),
                    'selected' => NavigationSelector::STANDARDISEDTEST_KEY
                ],
                'externalUser' => $externalUser,
                'user' => $user,
                'year' => $year,

                'subnavigation' => [
                    'all' => (new StandardisedTest\Navigation($this->getRouter(), $user, $externalUser, $db, $year))->getAvailableNavigation(),
                    'selected' => StandardisedTest\Navigation::STANDARDISEDTEST_MATHEMATICS_KEY
                ],

                'mathematics' => $mathematics
            ]
        ]);
    }


    /**
     * @param IConnector $db
     * @param User       $user
     * @param array      $externalUser
     * @param int|null   $year
     * @throws RouteNotFound
     */
    public function getStandardisedTestsLiteracyNumeracy(IConnector $db, User $user, array $externalUser, ?int $year): void
    {
        /* Default to this year if none specified
         */
        $selectedYear = $year ?? (int)date('Y');

        /* Literacy
         */
        $literacyPayload = (new LiteracyNumeracy\Literacy\Data($user, $externalUser, $db, $selectedYear));

        $literacy = [
            'data' => $literacyPayload->findData(),
            'visible' => (new LiteracyNumeracy\Literacy\ValidHasData())->isSatisfiedBy($user->getYear(), $literacyPayload->hasData())
        ];

        /* Numeracy
         */
        $numeracyPayload = (new LiteracyNumeracy\Numeracy\Data($user, $externalUser, $db, $selectedYear));

        $numeracy = [
            'data' => $numeracyPayload->findData(),
            'visible' => (new LiteracyNumeracy\Numeracy\ValidHasData())->isSatisfiedBy($user->getYear(), $numeracyPayload->hasData())
        ];

        $this->getResponse()->content->set([
            'template' => '@standardisedTest/LiteracyNumeracy/index.twig',
            'data' => [
                'navigation' => [
                    'all' => (new NavigationSelector($this->getRouter(), $user, $externalUser, $db, $year))->getAvailableNavigation(),
                    'selected' => NavigationSelector::STANDARDISEDTEST_KEY
                ],
                'externalUser' => $externalUser,
                'user' => $user,
                'year' => $year,

                'subnavigation' => [
                    'all' => (new StandardisedTest\Navigation($this->getRouter(), $user, $externalUser, $db, $year))->getAvailableNavigation(),
                    'selected' => StandardisedTest\Navigation::STANDARDISEDTEST_LITERACYNUMERACY_KEY
                ],

                'literacy' => $literacy,
                'numeracy' => $numeracy,
            ]
        ]);
    }


    /**
     * School Grades page
     *
     * @param IConnector $db
     * @param User       $user
     * @param array      $externalUser
     * @param int|null   $year
     * @throws RouteNotFound
     */
    public function getSchoolGrades(IConnector $db, User $user, array $externalUser, ?int $year): void
    {
        $footer = new SchoolGrades\Footer($user, $externalUser, $db, null);

        $gradeSummaryTable = new SchoolGrades\GradeSummary($user, $externalUser, $db, $year);
        $yearlyBreakdownTable = new SchoolGrades\YearlyBreakdown($user, $externalUser, $db, $year);
        $entriesAndResultsTable = new SchoolGrades\EntriesAndResults($user, $externalUser, $db, $year);

        $this->getResponse()->content->set([
            'template' => '@schoolGrades/index.twig',
            'data' => [
                'navigation' => [
                    'all' => (new NavigationSelector($this->getRouter(), $user, $externalUser, $db, $year))->getAvailableNavigation(),
                    'selected' => NavigationSelector::SCHOOLGRADES_KEY
                ],
                'footer' => [
                    'data' => $footer->findData(),
                ],
                'preselectedYear' => $year,
                'externalUser' => $externalUser,
                'user' => $user,

                'gradeSummary' => [
                    'heading' => $gradeSummaryTable->getHeading(),
                    'data' => $gradeSummaryTable->findData(),
                    'columns' => array_merge($gradeSummaryTable->getLetterRegistry(), ['availableGrades'])
                ],

                'yearlyBreakdown' => [
                    'heading' => $yearlyBreakdownTable->getHeading(),
                    'data' => $yearlyBreakdownTable->findData(),
                ],

                'entriesAndResults' => [
                    'heading' => $entriesAndResultsTable->getHeading(),
                    'data' => $entriesAndResultsTable->findData(),
                    'currentYear' => $entriesAndResultsTable->getCurrentYear()
                ]
            ]
        ]);
    }


    /**
     * IB page
     *
     * @param IConnector $db
     * @param User       $user
     * @param array      $externalUser
     * @param int|null   $year
     * @throws RouteNotFound
     */
    public function getIb(IConnector $db, User $user, array $externalUser, ?int $year): void
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
     * @param IConnector $db
     * @param User       $user
     * @param array      $externalUser
     * @param int|null   $year
     * @throws RouteNotFound
     */
    public function getNcea(IConnector $db, User $user, array $externalUser, ?int $year): void
    {
        $footer = new NCEA\Footer($user, $externalUser, $db, null);

        $literacyNumeracyCheckbox = new NCEA\LiteracyNumeracyCheckbox($user, $externalUser, $db, $year);
        $qualifications = new NCEA\Qualifications($user, $externalUser, $db, null);
        $creditSummary = new NCEA\CreditSummary($user, $externalUser, $db, null);

        $output = [
            'template' => '@ncea/index.twig',
            'data' => [
                'navigation' => [
                    'all' => (new NavigationSelector($this->getRouter(), $user, $externalUser, $db, $year))->getAvailableNavigation(),
                    'selected' => NavigationSelector::NCEA_KEY
                ],
                'footer' => [
                    'data' => $footer->findData(),
                ],
                'preselectedYear' => $year,
                'externalUser' => $externalUser,
                'user' => $user,
                'year' => $year,

                /* That checkbox thing at the top
                 */
                'literacyNumeracyCheckbox' => [
                    'heading' => $literacyNumeracyCheckbox->getHeading(),
                    'data' => $literacyNumeracyCheckbox->findData()
                ],

                /* Qualifications rating
                 */
                'qualifications' => [
                    'heading' => $qualifications->getHeading(),
                    'data' => $qualifications->findData(),
                ],

                /* Credit Summary graphs for all years combined
                 */
                'creditSummary' => [
                    'heading' => $creditSummary->getHeading(),
                    'data' => $creditSummary->findData(),
                    'allLetters' => $creditSummary->getLetterRegistry(),
                    'allTypes' => self::TYPES,

                    /* Suffix for graph div id's
                     */
                    'suffix' => 'all',
                ],
            ]
        ];

        /* The selected year should contain data. If a selected
         * year contains no data, probably 404 or something - you
         * could fiddle with the url to get illegal values
         */
        $navigation = new NCEA\CourseCreditSummaryNavigation($user, $externalUser, $db, null);
        $allYears = $navigation->findData();

        if (\count($allYears)) {
            /* Course Credit Summary year selectors
             */
            $output['data']['courseCreditSummaryNavigation'] = [
                'heading' => $navigation->getHeading(),
                'all' => $allYears,
                'selected' => $year
            ];

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
                $output['data']['creditSummaryYear'] = [
                    'data' => $creditSummaryYear->findData(),
                    'allLetters' => $creditSummaryYear->getLetterRegistry(),

                    /* Suffix for graph div id's
                     */
                    'suffix' => "year{$year}",
                ];

                /* Course Credit Summary for a specific year
                 */
                $output['data']['courseCreditSummary'] = [
                    'data' => $courseCreditSummary->findData(),
                    'allLetters' => $courseCreditSummary->getLetterRegistry(),
                ];

                /* Entries And Results for a specific year
                 */

                /* Get all the comments for $year for this user
                 * Then rearrange all the subjects
                 */
                $output['data']['entriesAndResults'] = [
                    'data' => $entriesAndResults->findData(),
                    'comments' => (new Comments\Comments($user, $externalUser, $db, $year))->findNCEAData(),
                    'allLetters' => $entriesAndResults->getLetterRegistry(),
                    'allTypes' => self::TYPES,
                    'year' => $year,
                    'currentYear' => $entriesAndResults->getCurrentYear()
                ];
            }
        }

        $this->getResponse()->content->set($output);
    }


    /**
     * Get all the comments for $externalUser
     *
     * @param IConnector $db
     * @param User       $user
     * @param array      $externalUser
     * @param int|null   $year
     * @throws RouteNotFound
     */
    public function getComments(IConnector $db, User $user, array $externalUser, ?int $year): void
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
     * @param IConnector $db
     * @param User       $user
     * @param array      $externalUser
     * @param int|null   $year
     * @throws RouteNotFound
     */
    public function getCoCurricular(IConnector $db, User $user, array $externalUser, ?int $year): void
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


    /**
     * @param IConnector $db
     * @param User       $user
     * @param array      $externalUser
     * @param int|null   $year
     * @throws RouteNotFound
     */
    public function getMyp(IConnector $db, User $user, array $externalUser, ?int $year): void
    {
        /* Default to this year if none specified
         */
        $currentYear  = (int)date('Y');
        $selectedYear = $year ?? $currentYear;
        $mypBase      = new MYP\Base($user, $externalUser, $db, $selectedYear);
        $mypData      = $mypBase->findData();

        // get historical data
        $currentYearLevel = (int)$user->getYear()->getName();
        $historical       = [];
        foreach ([7, 8, 9, 10] as $yearLevel) {
            if ($yearLevel === $currentYearLevel) {
                break;
            }
            $historicalYear              = $currentYear - ($currentYearLevel - $yearLevel);
            $historicalMypBase           = new MYP\Base($user, $externalUser, $db, $historicalYear);
            $historical[$historicalYear] = [
                'subjects' => $historicalMypBase->findData(),
                'grades'   => $historicalMypBase->getMYPGrade(),
            ];
        }

        $output = [
            'template' => '@myp/index.twig',
            'data'     => [
                'navigation'   => [
                    'all'      => (new NavigationSelector($this->getRouter(), $user, $externalUser, $db, $year))->getAvailableNavigation(),
                    'selected' => NavigationSelector::MYP_KEY,
                ],
                'externalUser' => $externalUser,
                'user'         => $user,
                'year'         => $currentYear,
                'headings'     => $mypData['headings'],
                'subjects'     => $mypData['data'],
                'grades'       => $mypBase->getMYPGrade(),
                'historical'   => $historical
            ],
        ];
        $this->getResponse()->content->set($output);
    }
}
