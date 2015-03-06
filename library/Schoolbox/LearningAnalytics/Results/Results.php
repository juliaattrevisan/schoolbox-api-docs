<?php
/**
 * Results
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics\Results;

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\OnlineAssessment;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Collection;
use Schoolbox\UserMapper;

class Results {

    /**
     * Unit instance
     *
     * @var UnitInstance
     */
    private $unit = null;

    /**
     * A collection of User instances
     *
     * @var Collection
     */
    private $allStudents = null;

    /**
     * An array of task_id-collection pairs, containing
     * all the students' marks for a particular task
     *
     * @var array
     */
    private $allValues = null;

    /**
     * A user mapper
     *
     * @var UserMapper
     */
    private $userMapper = null;

    /**
     * Constructor
     *
     * @param Collection $allStudents
     * @param UnitInstance $unit
     */
    public function __construct(Collection $allStudents, UnitInstance $unit) {
        $this->setAllStudents($allStudents);
        $this->setUnit($unit);
        $this->setUserMapper(new UserMapper());

        $this->process();
    }

    /**
     * Collate marks for each task, for each student
     */
    private function process() {
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
        foreach ($this->getUnit()->getTasks() as $currentTask) {
            if (($currentTask->getSubmissionBox() instanceof SubmissionBox) && is_numeric($currentTask->getSubmissionBox()->getId())) {
                $detail = new \DueWork_Detail($currentTask->getSubmissionBox()->getId(), $currentTask->getSubmissionBox());
                $detail->processStudents($this->getAllStudents());

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

        foreach ($this->getUnit()->getTasks() as $currentTask) {
            $allValues[$currentTask->getId()] = new Collection();

            /* Get all the stuff out of $studentSubmissionList[$submissionBoxId]
             */
            if (($currentTask->getSubmissionBox() instanceof SubmissionBox) && is_numeric($currentTask->getSubmissionBox()->getId())) {
                $allUsers = array_values($studentSubmissionList[$currentTask->getSubmissionBox()->getId()]);

                foreach ($allUsers as $currentUser) {
                    $allValues[$currentTask->getId()]->add(array(
                        'user' => $currentUser['user']['instance'],
                        'value' => $currentUser['return']['norm_mark']
                    ));
                }
            }
            else if (($currentTask->getAssessment() instanceof OnlineAssessment) && is_numeric($currentTask->getAssessment()->getId())) {
                $allUsers = array_values($studentQuizList[$currentTask->getAssessment()->getId()]);

                /* lol handlers
                 */
                foreach ($allUsers as $currentUser) {
                    $allValues[$currentTask->getId()]->add(array(
                        'user' => $this->getUserMapper()->findById($currentUser['user_id']),
                        'value' => $currentUser['totalMarks']
                    ));
                }
            }
        }

        $this->setAllValues($allValues);
    }

    /* Getters/Setters
     */

    /**
     * Set unit
     *
     * @param UnitInstance $unit
     */
    private function setUnit($unit)
    {
        $this->unit = $unit;
    }

    /**
     * Get unit
     *
     * @return UnitInstance
     */
    private function getUnit()
    {
        return $this->unit;
    }

    /**
     * Set all values
     *
     * @param array $allValues
     */
    private function setAllValues($allValues)
    {
        $this->allValues = $allValues;
    }

    /**
     * Get all values
     *
     * @return array
     */
    public function getAllValues()
    {
        return $this->allValues;
    }

    /**
     * Set all students
     *
     * @param \Schoolbox\Collection $allStudents
     */
    private function setAllStudents($allStudents)
    {
        $this->allStudents = $allStudents;
    }

    /**
     * Get all students
     *
     * @return \Schoolbox\Collection
     */
    private function getAllStudents()
    {
        return $this->allStudents;
    }

    /**
     * Set user mapper
     *
     * @param \Schoolbox\UserMapper $userMapper
     */
    private function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
    }

    /**
     * Get user mapper
     *
     * @return \Schoolbox\UserMapper
     */
    private function getUserMapper()
    {
        return $this->userMapper;
    }
}