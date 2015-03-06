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
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\OnlineAssessment;

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
        $sectionHandler = new \Assess_Section();
        $attemptHandler = new \Assess_Attempt();

        $studentSubmissionList = array();
        $studentQuizList = array();

        /* Submission box id may not actually exist, even though there
         * may be a submission box instance. That's when tasks exist but
         * aren't associated with a submission box yet.
         *
         * Similarly with quizzes
         */
        foreach ($unit->getTasks() as $currentTask) {
            if (($currentTask->getSubmissionBox() instanceof SubmissionBox) && is_numeric($currentTask->getSubmissionBox()->getId())) {
                $detail = new \DueWork_Detail($currentTask->getSubmissionBox()->getId(), $currentTask->getSubmissionBox());
                $detail->processStudents($allStudents);

                $studentSubmissionList[$currentTask->getSubmissionBox()->getId()] = $detail->getSitepageData();
            }
            else if (($currentTask->getAssessment() instanceof OnlineAssessment) && is_numeric($currentTask->getAssessment()->getId())) {

                // attempt_list is a list of all valid attempts, ordered chronologically.
                // Filter to most recent submission only.
                $allAttempts = $attemptHandler->getList($currentTask->getAssessment()->getId());

                if (!empty($allAttempts)) {
                    foreach ($allAttempts as $currentAttempt) {
                        $studentQuizList[$currentTask->getAssessment()->getId()][$currentAttempt['user_id']] = $currentAttempt;
                    }

                    // Load max mark, because getList doesn't get it.
                    foreach ($studentQuizList[$currentTask->getAssessment()->getId()] as &$currentAttempt) {
                        $currentAttempt['maxMark']      = $attemptHandler->getTotalMarks($currentTask->getAssessment()->getId(), $currentAttempt['attempt_id']);
                        $currentAttempt['firstSection'] = $sectionHandler->getFirst($currentTask->getAssessment()->getId());
                    }

                    /* That stupid bug again
                     */
                    unset($currentAttempt);
                }
            }
        }

        /* Voltron those two lists together, distilling data out of
         * both $studentSubmissionList and $studentQuizList
         *
         * This could probably be done in the above loops though
         */
        $allValues = array();
        
        foreach ($unit->getTasks() as $currentTask) {
            $allValues[$currentTask->getId()] = new Collection();

            /* Get all the stuff out of $studentSubmissionList[$submissionBoxId]
             */
            if (($currentTask->getSubmissionBox() instanceof SubmissionBox) && is_numeric($currentTask->getSubmissionBox()->getId())) {
                $allUsers = array_values($studentSubmissionList[$currentTask->getSubmissionBox()->getId()]);

                foreach ($allUsers as $currentUser) {
                    $allValues[$currentTask->getId()]->add($currentUser['return']['norm_mark']);
                }
            }
            else if (($currentTask->getAssessment() instanceof OnlineAssessment) && is_numeric($currentTask->getAssessment()->getId())) {
                $allUsers = array_values($studentQuizList[$currentTask->getAssessment()->getId()]);

                foreach ($allUsers as $currentUser) {
                    $allValues[$currentTask->getId()]->add($currentUser['totalMarks']);
                }
            }
        }

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
                    'results' => array(
                        'allTasks' => $unit->getTasks(),
                        'allValues' => $allValues,
                    ),
                )
            )
        );
    }
}