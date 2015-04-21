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
        $gradeSummaryTable = new SchoolGrades\GradeSummary($externalUser, $db);
        $yearlyBreakdownTable = new SchoolGrades\YearlyBreakdown($externalUser, $db);
        $entriesAndResultsTable = new SchoolGrades\EntriesAndResults($externalUser, $db);

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
        $progressGradeSummary = new IB\ProgressGradeSummary($externalUser, $db);
        $yearlyBreakdown = new IB\YearlyBreakdown($externalUser, $db);

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
        $literacyNumeracyCheckbox = new NCEA\LiteracyNumeracyCheckbox($externalUser, $db);
        $creditSummary = new NCEA\CreditSummary($externalUser, $db);

        $this->getResponse()->content->set(array(
            'template' => '@ncea/index.twig',
            'data' => array(
                'preselectedYear' => $year,
                'externalUser' => $externalUser,
                'user' => $user,

                'literacyNumeracyCheckbox' => array(
                    'heading' => $literacyNumeracyCheckbox->getHeading(),
                    'data' => $literacyNumeracyCheckbox->findData()
                ),

                'creditSummary' => array(
                    'heading' => $creditSummary->getHeading(),
                    'data' => $creditSummary->findData(),
                    'allLetters' => $creditSummary->getLetterRegistry(),
                    'allTypes' => array('IN', 'EX')
                )
            )
        ));
    }
} 