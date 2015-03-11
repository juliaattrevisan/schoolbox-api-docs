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
use Schoolbox\LearningAnalytics\Progress\Overall;
use Schoolbox\LearningAnalytics\Progress\Unit;
use Schoolbox\LearningAnalytics\Results\Results;
use Schoolbox\LearningAnalytics\Results\Averages;

class Controller extends Base {

    /**
     * Do everything
     *
     * $folder is the Folder for which the Units/Courses are relevant
     *
     * $unit is the currently selected unit for $folder. That is used for
     * all the boxplots, rankings etc.
     *
     * $allUnits is all the units in the course. That is used to calculate
     * overall progress etc. To make it relevant to only one unit, fill
     * it with only one unit instead of all the units.
     *
     * @param Folder $folder
     * @param UnitInstance $unit
     * @param Collection $allUnits
     */
    public function index(Folder $folder, UnitInstance $unit, Collection $allUnits) {

        /* Overall Progress
         */
        $overallProgress = new Overall($folder, $allUnits);

        /* Results
         */
        $studentListProcessor = new StudentList($folder, $unit);
        $allStudents = $studentListProcessor->getStudentList();

        $results = new Results($allStudents, $unit);
        $averages = new Averages($allStudents, $unit);

        /* Individual Unit Progress
         */
        $allUnitProgress = array();

        foreach ($allUnits as $currentUnit) {
            $studentListProcessor = new StudentList($folder, $currentUnit);
            $allStudents = $studentListProcessor->getStudentList();

            $unitProgress = new Unit($allStudents, $currentUnit);

            $allUnitProgress[] = array(
                'unit' => $currentUnit,
                'mostAdvancedStudent' => $unitProgress->getMostAdvancedStudent(),
                'classCompletions' => $unitProgress->getClassCompletions()
            );
        }

        /* Twig data
         */
        $this->getResponse()->content->set(
            array(
                'template' => '@learningAnalytics/index.twig',
                'data' => array(
                    'currentUnit' => $unit,
                    'overallProgress' => array(
                        'mostAdvancedStudent' => $overallProgress->getMostAdvancedStudent(),
                        'classCompletions' => $overallProgress->getClassCompletions(),
                    ),
                    'results' => array(
                        'allTasks' => $unit->getTasks(),
                        'allValues' => $results->getAllValues(),
                        'topStudents' => $averages->findDescending(),
                        'bottomStudents' => $averages->findAscending(),
                    ),
                    'progress' => array(
                        'allUnits' => $allUnitProgress,
                    ),
                )
            )
        );
    }
}