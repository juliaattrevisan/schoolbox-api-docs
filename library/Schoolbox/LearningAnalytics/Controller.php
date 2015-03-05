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

                $topStudents->add($currentTaskCompletion);
            }
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

        $this->getResponse()->content->set(
            array(
                'template' => '@learningAnalytics/index.twig',
                'data' => array(
                    'topStudents' => $topStudents,
                    'bottomStudents' => $bottomStudents,
                )
            )
        );
    }
}