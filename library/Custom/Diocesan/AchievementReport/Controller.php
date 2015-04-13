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

class Controller extends Base {

    /**
     * Index
     */
    public function index() {
        $db = new MSSQL();

        $gradeSummaryTable = new SchoolGrades\GradeSummaryTable($db);

        $this->getResponse()->content->set(array(
            'template' => '@achievementreport/index.twig',
            'data' => array(
                'gradeSummaryTable' => $gradeSummaryTable->findData()
            )
        ));
    }
} 