<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Test;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Plan;
use Schoolbox\Learning\Assessment\Project\ResultCache;
use Schoolbox\Assessment\Participant\Plan\Mapper as PlanMapper;
use Schoolbox\User;

/**
 * Convenience class to encapsulate logic required to bring unit test up
 * to new Participant and Plan domain model graph
 */
class ParticipantLinker
{
    /**
     * Create a participant in the specified plan, and link them all up
     *
     * @param User $user
     * @param Plan $plan
     * @return Participant
     */
    public function createAndLinkParticipant(User $user, Plan $plan): Participant
    {
        $resultCacheMapper = ResultCache\Mapper::getInstance();

        $participant                             = new Participant($user, $plan, $resultCacheMapper);
        $plan->getParticipants()[$user->getId()] = $participant;
        return $participant;
    }


    /**
     * Creates a plan and links it up with the IAssessable
     *
     * @param IAssessable $assessable
     * @param int        $isDynamic
     * @return Plan
     */
    public function createAndLinkPlan(IAssessable $assessable, int $isDynamic = PlanMapper::PLAN_TYPE_DYNAMIC): Plan
    {
        $plan = (new Plan($assessable))
            ->setId($assessable->getId())
            ->setIsDynamic($isDynamic)
            ->setCreatedAt($assessable->getCreatedAt() ? clone $assessable->getCreatedAt() : null)
            ->setUpdatedAt($assessable->getUpdatedAt() ? clone $assessable->getUpdatedAt() : null)
            ->setDeletedAt($assessable->getDeletedAt() ? clone $assessable->getDeletedAt() : null);
        $assessable->getPlans()->add($plan);
        return $plan;
    }
}
