<?php
/**
 * ValidUserViewsAssessment
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
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

class ValidUserViewsAssessment
{
    /**
     * @var Plan\Mapper
     */
    private $planMapper;

    /**
     * @var ParticipantMapper
     */
    private $participantMapper;

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
    private function isIncluded(Participant $participant): bool
    {
        return !$participant->isExcluded();
    }

    /**
     * Instructors can try their quiz to see what it does
     *
     * @param User         $user
     * @param Folder\Model $folder
     * @return bool
     */
    private function isInstructor(User $user, Folder\Model $folder): bool
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
    private function hasDynamicPlan($assessmentId): bool
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
     * @param int          $assessmentId
     * @return bool
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     */
    public function isSatisfiedBy(User $user, Folder\Model $folder, int $assessmentId): bool
    {
        // Check user can participate
        $participant = $this->participantMapper->findForAssessmentAndUser(
            Assessment\Mapper::getInstance()->createModel($assessmentId),
            $user
        );

        if ($participant) {
            return $this->isIncluded($participant);
        }

        if ($this->isInstructor($user, $folder)) {
            return true;
        }

        return $this->hasDynamicPlan($assessmentId);
    }
}
