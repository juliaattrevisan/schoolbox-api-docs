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
     * Index
     */
    public function schoolGrades($externalUser) {
        $db = new MSSQL();

        $gradeSummaryTable = new SchoolGrades\GradeSummaryTable($db);

        $this->getResponse()->content->set(array(
            'template' => '@achievementreport/index.twig',
            'data' => array(
                'gradeSummary' => array(
                    'externalUser' => $externalUser,
                    'heading' => $gradeSummaryTable->getHeading(),
                    'data' => $gradeSummaryTable->findData($externalUser['StudentID'])
                )
            )
        ));
    }
} 