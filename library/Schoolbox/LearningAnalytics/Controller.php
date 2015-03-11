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
use Schoolbox\LearningAnalytics\Results\Results;
use Schoolbox\LearningAnalytics\Results\Averages;

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

        /* Overall Progress
         */
        $overallProgress = new OverallProgress($allStudents, $unit);

        /* Results
         */
        $results = new Results($allStudents, $unit);
        $averages = new Averages($allStudents, $unit);

        $this->getResponse()->content->set(
            array(
                'template' => '@learningAnalytics/index.twig',
                'data' => array(
                    'overallProgress' => array(
                        'mostAdvancedStudent' => $overallProgress->getMostAdvancedStudent(),
                        'classCompletions' => $overallProgress->getClassCompletions(),
                        'totalTasks' => $unit->getTasks()->count(),
                    ),
                    'results' => array(
                        'allTasks' => $unit->getTasks(),
                        'allValues' => $results->getAllValues(),
                        'topStudents' => $averages->findDescending(),
                        'bottomStudents' => $averages->findAscending(),
                    ),
                )
            )
        );
    }
}