<?php
/**
 * ValidUserCanInteractWithAssessment
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Quiz;

use Schoolbox\Assessment\Participant\Mapper as ParticipantMapper;
use Schoolbox\Assessment\Participant\Model as Participant;
use Schoolbox\Assessment\Participant\Plan;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\Quiz;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

class ValidUserCanInteractWithAssessment
{
    /**
     * @var Plan\Mapper
     */
    protected $planMapper;

    /**
     * @var ParticipantMapper
     */
    protected $participantMapper;

    /**
     * ValidUserViewsAssessment constructor.
     *
     * @param Plan\Mapper       $participantPlanMapper
     * @param ParticipantMapper $participantMapper
     */
    public function __construct(Plan\Mapper $participantPlanMapper, ParticipantMapper $participantMapper)
    {
        $this->planMapper = $participantPlanMapper;
        $this->participantMapper = $participantMapper;
    }

    /**
     * Only included (non-excluded) participants allowed
     *
     * @param Participant $participant
     * @return bool
     */
    protected function isIncluded(Participant $participant): bool
    {
        return !$participant->isExcluded();
    }

    /**
     * Performs a check for Instructor users
     *
     * Instructors can try their quiz to see what it does
     *
     * @param User         $user
     * @param Folder\Model $folder
     * @return bool
     */
    protected function instructorCheck(User $user, Folder\Model $folder): bool
    {
        return Access::getInstance()->isInstructor($user, $folder);
    }

    /**
     * 1. Only included (non-excluded) people can do this quiz
     * 2. User might be an Instructor; Instructors can do the quiz for testing
     * 3. Try a dynamic plan
     * 4. Deny access
     *
     * @param User         $user
     * @param Folder\Model $folder
     * @param Quiz\Model   $assessment
     * @return bool
     * @throws Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function isSatisfiedBy(User $user, Folder\Model $folder, Quiz\Model $assessment): bool
    {
        // Check user can participate
        $participant = $this->participantMapper->findForAssessmentAndUser(
            Assessment\Mapper::getInstance()->createModel($assessment->getId()),
            $user
        );

        if ($participant && $this->isIncluded($participant)) {

            /* Looks like everyone has a Plan in some way, regardless if it's custom or default?
             */
            $participant = $assessment->getParticipant($user);

            /* Is currently open - allow
             */
            if (\Alaress_Date_Format::now()->lt($participant->getCloseDate())) {
                return true;
            }
            else {
                // TODO: Error out?
            }

            /* Has opened - allow
             */
            if (\Alaress_Date_Format::now()->gt($participant->getOpenDate())) {
                return true;
            }
            else {
                // TODO: Error out?
            }

            /* Included instructors can get in
             */
            return $this->instructorCheck($user, $folder);
        }

        /* You can't get in under any other circumstance
         */
        return false;
    }
}