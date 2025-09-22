<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Participant;

use Schoolbox\Assessment\Participant\Plan\Mapper as PlanMapper;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Plan;
use Schoolbox\Learning\Assessment\Project;
use UnexpectedValueException;

/**
 * Encapsulated in its own class for re-usability
 */
class Aggregator
{
    /**
     * @param Project\ResultCache\Mapper $projectResultCacheMapper
     */
    public function __construct(private Project\ResultCache\Mapper $projectResultCacheMapper)
    {
    }


    /**
     * To maintain our interface methods, we need to insert a non-persisted plan and set of participant
     * objects on the Project domain object.
     *
     * eg, if a Project contains assessments A and B, and Assessments A and B contain users [1,2] and [2,3]
     *     then the Project needs to contain participants for users [1,2.3];
     *
     * The project participants do not have IResponses or IFeedbacks
     *
     * @param Project $project
     * @throws UnexpectedValueException If unable to merge collections
     */
    public function aggregate(Project $project): void
    {
        // Create a Project plan to hold the participants. It is dynamic if any subAssessment has a dynamic plan.
        $isDynamic = PlanMapper::PLAN_TYPE_STATIC;
        foreach ($project->getAssessables() as $assessable) {
            if ($assessable->getDynamicPlan()) {
                $isDynamic = PlanMapper::PLAN_TYPE_DYNAMIC;
                break;
            }
        }
        $plan = (new Plan($project))->setIsDynamic($isDynamic);
        $project->getPlans()->add($plan);

        // Add participant objects
        foreach ($project->getParticipantUsers() as $user) {
            $participant                             = new Participant($user, $plan, $this->projectResultCacheMapper);
            $plan->getParticipants()[$user->getId()] = $participant;
        }
    }
}
