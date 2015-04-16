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
        $gradeSummaryTable = new SchoolGrades\GradeSummaryTable($externalUser, $db);
        $yearlyBreakdownTable = new SchoolGrades\YearlyBreakdownTable($externalUser, $db);
        $entriesAndResultsTable = new SchoolGrades\EntriesAndResultsTable($externalUser, $db);

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
} 