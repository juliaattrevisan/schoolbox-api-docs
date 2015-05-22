<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport;

use Alaress\Web\Controller\Base;
use MSSQL;
use Alaress_Probe as Probe;
use Schoolbox\User;

class Controller extends Base {

    /**
     * Internal/External types
     *
     * Maps to css classes
     *
     * @var array
     */
    private $types = array(
        'IN' => array(
            'long' => 'Internally Assessed',
            'short' => 'Internal',
            'css' => 'type-pink',
        ),
        'EX' => array(
            'long' => 'Externally Assessed',
            'short' => 'External',
            'css' => 'type-blue'
        )
    );

    /**
     * Selects which report to redirect towards, based on certain
     * conditions particular to a student
     *
     * @param $db
     * @param $user
     * @param $externalUser
     * @param $year
     */
    public function selector($db, User $user, $externalUser, $year) {
        $selector = new NavigationSelector($db, $user, $externalUser);
        $selector->determineDefaultReport();

        $report = $selector->getDefaultReport();

        switch ($report) {
            case 'schoolGrades':
                $this->schoolGrades($db, $user, $externalUser, $year);
                break;

            case 'ib':
                $this->ib($db, $user, $externalUser, $year);
                break;

            case 'ncea':
                $this->ncea($db, $user, $externalUser, $year);
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
     * School Grades page
     */
    public function schoolGrades($db, $user, $externalUser, $year) {
        $selector = new NavigationSelector($db, $user, $externalUser);
        $footer = new SchoolGrades\Footer($externalUser, $db, null);

        $gradeSummaryTable = new SchoolGrades\GradeSummary($externalUser, $db, $year);
        $yearlyBreakdownTable = new SchoolGrades\YearlyBreakdown($externalUser, $db, $year);
        $entriesAndResultsTable = new SchoolGrades\EntriesAndResults($externalUser, $db, $year);

        $this->getResponse()->content->set(array(
            'template' => '@schoolgrades/index.twig',
            'data' => array(
                'navigation' => array(
                    'all' => $selector->getAvailableNavigation(),
                    'selected' => 'schoolgrades'
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
     * @param $db
     * @param $user
     * @param $externalUser
     * @param $year
     */
    public function ib($db, $user, $externalUser, $year) {
        $selector = new NavigationSelector($db, $user, $externalUser);
        $footer = new IB\Footer($externalUser, $db, null);

        $progressGradeSummary = new IB\ProgressGradeSummary($externalUser, $db, $year);
        $yearlyBreakdown = new IB\YearlyBreakdown($externalUser, $db, $year);

        $this->getResponse()->content->set(array(
            'template' => '@ib/index.twig',
            'data' => array(
                'navigation' => array(
                    'all' => $selector->getAvailableNavigation(),
                    'selected' => 'ib'
                ),
                'footer' => array(
                    'data' => $footer->findData(),
                ),
                'preselectedYear' => $year,
                'externalUser' => $externalUser,
                'user' => $user,

                'progressGradeSummary' => array(
                    'heading' => $progressGradeSummary->getHeading(),
                    'data' => $progressGradeSummary->findData(),
                ),

                'yearlyBreakdown' => array(
                    'heading' => $yearlyBreakdown->getHeading(),
                    'data' => $yearlyBreakdown->findData(),
                    'currentYear' => $yearlyBreakdown->getCurrentYear()
                )
            )
        ));
    }

    /**
     * NCEA page
     *
     * @param $db
     * @param $user
     * @param $externalUser
     * @param $year
     */
    public function ncea($db, $user, $externalUser, $year) {
        $selector = new NavigationSelector($db, $user, $externalUser);
        $footer = new NCEA\Footer($externalUser, $db, null);

        $literacyNumeracyCheckbox = new NCEA\LiteracyNumeracyCheckbox($externalUser, $db, $year);
        $qualifications = new NCEA\Qualifications($externalUser, $db, null);
        $creditSummary = new NCEA\CreditSummary($externalUser, $db, null);

        $output = array(
            'template' => '@ncea/index.twig',
            'data' => array(
                'navigation' => array(
                    'all' => $selector->getAvailableNavigation(),
                    'selected' => 'ncea'
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
                    'allTypes' => $this->getTypes(),

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
        $navigation = new NCEA\CourseCreditSummaryNavigation($externalUser, $db, null);
        $allYears = $navigation->findData();

        if (count($allYears)) {

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
                $creditSummaryYear = new NCEA\CreditSummary($externalUser, $db, $year);
                $courseCreditSummary = new NCEA\CourseCreditSummary($externalUser, $db, $year);
                $entriesAndResults = new NCEA\EntriesAndResults($externalUser, $db, $year);

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
                $output['data']['entriesAndResults'] = array(
                    'data' => $entriesAndResults->findData(),
                    'allLetters' => $entriesAndResults->getLetterRegistry(),
                    'allTypes' => $this->getTypes(),
                );
            }
        }

        $this->getResponse()->content->set($output);
    }

    /* Getters/Setters
     */

    /**
     * Set types
     *
     * @param array $types
     */
    private function setTypes($types)
    {
        $this->types = $types;
    }

    /**
     * Get types
     *
     * @return array
     */
    private function getTypes()
    {
        return $this->types;
    }
}