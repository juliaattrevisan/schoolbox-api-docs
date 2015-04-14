<?php
/**
 * Results
 *
 * Get results for a specific unit
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

class Unit {

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
                $detail = new \DueWork_Detail($currentTask->getSubmissionBox(), $currentTask->getSubmissionBox());
                $detail->processStudents($this->getAllStudents());

                $studentSubmissionList[$currentTask->getSubmissionBox()->getId()] = $detail->getSitepageData();
            }
            else if (($currentTask->getAssessment() instanceof OnlineAssessment) && is_numeric($currentTask->getAssessment()->getId())) {

                // attempt_list is a list of all valid attempts, ordered chronologically.
                // Filter to most recent submission only.
                $allAttempts = $attemptHandler->getList($currentTask->getAssessment()->getId());

                if (!empty($allAttempts)) {
                    foreach ($allAttempts as $currentAttempt) {

                        /* Manipulate a bit more
                         * Load max mark, because getList doesn't get it.
                         */
                        $temp = $currentAttempt;
                        $temp['maxMark'] = $attemptHandler->getTotalMarks($currentTask->getAssessment()->getId(), $temp['attempt_id']);
                        $temp['firstSection'] = $sectionHandler->getFirst($currentTask->getAssessment()->getId());

                        $studentQuizList[$currentTask->getAssessment()->getId()][$temp['user_id']] = $temp;
                    }
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

            /* Group by originating task id, not task instance id
             */
            $allValues[$currentTask->getTask()->getId()] = new Collection();

            /* Get all the stuff out of $studentSubmissionList[$submissionBoxId]
             */
            if (($currentTask->getSubmissionBox() instanceof SubmissionBox) && is_numeric($currentTask->getSubmissionBox()->getId())) {
                $allUsers = array_values($studentSubmissionList[$currentTask->getSubmissionBox()->getId()]);

                foreach ($allUsers as $currentUser) {
                    $allValues[$currentTask->getTask()->getId()]->add(array(
                        'user' => $currentUser['user']['instance'],
                        'value' => $currentUser['return']['norm_mark']
                    ));
                }
            }
            else if (($currentTask->getAssessment() instanceof OnlineAssessment) && is_numeric($currentTask->getAssessment()->getId())) {
                $users = $studentQuizList[$currentTask->getAssessment()->getId()];

                if (is_array($users) && count($users)) {
                    $allUsers = array_values($users);

                    /* Only accumulate if an attempt was made and the maximum available
                     * marks for the quiz is greater than zero
                     */
                    foreach ($allUsers as $currentUser) {
                        if ($currentUser['totalMarks'] !== null) {
                            if ($currentUser['maxMark'] > 0) {
                                $normalised = 100 * $currentUser['totalMarks']/$currentUser['maxMark'];

                                $allValues[$currentTask->getTask()->getId()]->add(array(
                                    'user' => $this->getUserMapper()->findById($currentUser['user_id']),
                                    'value' => $normalised
                                ));
                            }
                        }
                    }
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
     * @param Collection $allStudents
     */
    private function setAllStudents($allStudents)
    {
        $this->allStudents = $allStudents;
    }

    /**
     * Get all students
     *
     * @return Collection
     */
    private function getAllStudents()
    {
        return $this->allStudents;
    }

    /**
     * Set user mapper
     *
     * @param UserMapper $userMapper
     */
    private function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
    }

    /**
     * Get user mapper
     *
     * @return UserMapper
     */
    private function getUserMapper()
    {
        return $this->userMapper;
    }
}