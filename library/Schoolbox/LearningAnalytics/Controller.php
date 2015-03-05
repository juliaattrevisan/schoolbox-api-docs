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

class Controller extends Base {

    /**
     * Number of chumps that can appear in the
     * Halls of Fame and Shame
     *
     * @var int
     */
    private $maxStudentThreshold = 3;

    public function index(Folder $folder, UnitInstance $unit, Collection $allUnits) {
        $studentListProcessor = new StudentList($folder, $unit);
        $allStudents = $studentListProcessor->getStudentList();

        $taskCompletions = new TaskCompletions($allStudents, $unit);

        /* A list of the top N students
         *
         * These are people who have completed the most tasks,
         * sorted by count in descending order
         */
        $topStudents = new Collection();

        $allTaskCompletions = $taskCompletions->findDescending();

        for ($i = 0; $i < $this->maxStudentThreshold; $i++) {
            if (isset($allTaskCompletions[$i])) {
                $currentTaskCompletion = $allTaskCompletions[$i];

                /* Top students should have at least one completed
                 * item in order to be listed here
                 */
                if (count($currentTaskCompletion['tasks'])) {
                    $topStudents->add($currentTaskCompletion);
                }
            }
        }

        /* This, coincidentally, helps you determine the racehorses
         * of the class which are represented by the orange bar
         *
         * That bar tells you how many tasks a student has completed
         * as a percentage of all the available tasks (horizontal
         * ticks on the progressReport screen)
         *
         * Expressed as a percentage
         */
        $mostAdvancedStudent = 0;

        if ($topStudents->count() && $unit->getTasks()->count()) {
            $topStudent = $topStudents->first();
            $mostAdvancedStudent = 100 * count($topStudent['tasks'])/$unit->getTasks()->count();
        }

        /* A list of the bottom N students
         *
         * These are people who have completed the most tasks,
         * sorted by count in ascending order
         */
        $bottomStudents = new Collection();

        $allTaskCompletions = $taskCompletions->findAscending();

        for ($i = 0; $i < $this->maxStudentThreshold; $i++) {
            if (isset($allTaskCompletions[$i])) {
                $currentTaskCompletion = $allTaskCompletions[$i];

                $bottomStudents->add($currentTaskCompletion);
            }
        }

        /* Find out the overall progress throughout all tasks
         * This is the number of tasks that all students have
         * marked as completed, represented as a percentage
         * of all the tasks that are available
         */
        $completionCounts = array();

        foreach ($allTaskCompletions as $currentTaskCompletion) {
            foreach ($currentTaskCompletion['tasks'] as $currentTask) {
                $completionCounts[$currentTask]++;
            }
        }

        $classCompletions = 0;

        foreach ($completionCounts as $key => $value) {
            if ($value == $allStudents->count()) {
                $classCompletions++;
            }
        }

        if ($unit->getTasks()->count()) {
            $classCompletions = 100 * $classCompletions / $unit->getTasks()->count();
        }
        else {
            $classCompletions = 0;
        }

        $this->getResponse()->content->set(
            array(
                'template' => '@learningAnalytics/index.twig',
                'data' => array(
                    'topStudents' => $topStudents,
                    'bottomStudents' => $bottomStudents,
                    'mostAdvancedStudent' => $mostAdvancedStudent,
                    'classCompletions' => $classCompletions,
                )
            )
        );
    }
}