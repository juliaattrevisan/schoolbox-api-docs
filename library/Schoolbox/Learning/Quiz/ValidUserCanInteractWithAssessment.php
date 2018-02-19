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
    protected function isInstructor(User $user, Folder\Model $folder): bool
    {
        return Access::getInstance()->isInstructor($user, $folder);
    }

    /**
     * Does a dynamic plan exist?
     *
     * @param $assessmentId
     * @return bool
     * @throws Mapper\DefinitionException
     */
    protected function hasDynamicPlan($assessmentId): bool
    {
        $plans = $this->planMapper->findPlansAndParticipantsForAssessments([ $assessmentId ]);

        /* If plans existed, investigate each
         *
         * While investigating, if you found a dynamic plan, let them in
         * Otherwise deny access
         */

        /** @var Plan\Model $plan */
        foreach ($plans as $plan) {
            if ($plan->isDynamic()) {
                return true;
            }
        }

        return false;
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
     * @throws Mapper\DefinitionException
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

        /* If user is a participant, and they're included in the assessment,
         * check the dates to see if they can get in
         *
         * Otherwise, if they've been excluded, they definitely get kicked out
         * A user had to be a participant in order to be excluded
         */
        if ($participant) {
            if ($this->isIncluded($participant)) {

                /* Looks like everyone has a Plan in some way, regardless if it's custom or default?
                 */
                $participantDetails = $assessment->getParticipant($user);

                /* Is open and hasn't closed yet - allow
                 */
                if (\Alaress_Date_Format::now()->gt($participantDetails->getOpenDate())) {
                    if (\Alaress_Date_Format::now()->lt($participantDetails->getCloseDate())) {
                        return true;
                    }
                    else {
                        // TODO: error(143) ?
                    }
                } else {
                    // TODO: error(120) ?
                }
            }

            /* You were excluded. You cannot be here.
             */
            return false;
        }

        /* Instructors can always preview it initially
         *
         * They won't be included in the class list, so they won't have a dynamic plan,
         * but they should be able to get in
         */
        if ($this->isInstructor($user, $folder)) {
            return true;
        }

        /* This occurs when you don't have a participation record
         * Such a record spawns upon first interaction (apparently)
         *
         * If you're just a regular schmoe (i.e. not an instructor) you are beholden to the class list as defined
         * by the SIS. If you're in it, you can get in (you "have a dynamic plan")
         *
         * Otherwise, you cannot be here because you weren't in the class list, and you aren't an instructor of
         * any kind, so you're not meant to get in
         */
        return $this->hasDynamicPlan($assessment->getId());
    }
}