<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics;

use Alaress\Web\Controller\Base;

use Schoolbox\Resource\Category as Folder;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Collection;
use Schoolbox\LearningAnalytics\OverallProgress\OverallProgress;

class Controller extends Base {

    /**
     * Do everything
     *
     * @param Folder $folder
     * @param UnitInstance $unit
     * @param Collection $allUnits
     */
    public function index(Folder $folder, UnitInstance $unit, Collection $allUnits) {
        $studentListProcessor = new StudentList($folder, $unit);
        $allStudents = $studentListProcessor->getStudentList();

        $overallProgress = new OverallProgress($allStudents, $unit);

        $this->getResponse()->content->set(
            array(
                'template' => '@learningAnalytics/index.twig',
                'data' => array(
                    'overallProgress' => array(
                        'topStudents' => $overallProgress->getTopStudents(),
                        'bottomStudents' => $overallProgress->getBottomStudents(),
                        'mostAdvancedStudent' => $overallProgress->getMostAdvancedStudent(),
                        'classCompletions' => $overallProgress->getClassCompletions(),
                        'totalTasks' => $unit->getTasks()->count(),
                    ),
                )
            )
        );
    }
}