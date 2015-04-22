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

class Controller extends Base {

    /**
     * School Grades page
     */
    public function schoolGrades($db, $user, $externalUser, $year) {
        $gradeSummaryTable = new SchoolGrades\GradeSummary($externalUser, $db, $year);
        $yearlyBreakdownTable = new SchoolGrades\YearlyBreakdown($externalUser, $db, $year);
        $entriesAndResultsTable = new SchoolGrades\EntriesAndResults($externalUser, $db, $year);

        $this->getResponse()->content->set(array(
            'template' => '@schoolgrades/index.twig',
            'data' => array(
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
        $progressGradeSummary = new IB\ProgressGradeSummary($externalUser, $db, $year);
        $yearlyBreakdown = new IB\YearlyBreakdown($externalUser, $db, $year);

        $this->getResponse()->content->set(array(
            'template' => '@ib/index.twig',
            'data' => array(
                'preselectedYear' => $year,
                'externalUser' => $externalUser,
                'user' => $user,

                'progressGradeSummary' => array(
                    'heading' => $progressGradeSummary->getHeading(),
                    'data' => $progressGradeSummary->findData(),
                ),

                'yearlyBreakdown' => array(
                    'heading' => $yearlyBreakdown->getHeading(),
                    'data' => $yearlyBreakdown->findData()
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
        $literacyNumeracyCheckbox = new NCEA\LiteracyNumeracyCheckbox($externalUser, $db, $year);
        $creditSummary = new NCEA\CreditSummary($externalUser, $db, false);

        $output = array(
            'template' => '@ncea/index.twig',
            'data' => array(
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

                /* Credit Summary graphs for all years combined
                 */
                'creditSummary' => array(
                    'heading' => $creditSummary->getHeading(),
                    'data' => $creditSummary->findData(),
                    'allLetters' => $creditSummary->getLetterRegistry(),
                    'allTypes' => array('IN', 'EX'),

                    /* Suffix for graph div id's
                     */
                    'suffix' => 'all',
                ),
            )
        );

        /* Conditionally, if a year was selected; do another round of
         * processing specific to that year. Otherwise skip all this
         * shenanigans altogether
         */
        if ($year) {
            $creditSummaryYear = new NCEA\CreditSummary($externalUser, $db, $year);

            /* Credit Summary graphs for a specific year
             */
            $output['data']['creditSummaryYear'] = array(
                'data' => $creditSummaryYear->findData(),
                'allLetters' => $creditSummaryYear->getLetterRegistry(),

                /* Suffix for graph div id's
                 */
                'suffix' => "year{$year}",
            );
        }

        $this->getResponse()->content->set($output);
    }
} 