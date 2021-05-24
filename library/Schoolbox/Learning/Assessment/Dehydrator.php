<?php

declare(strict_types=1);

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Schoolbox\Learning\Assessment\DueWork\Model as DueWorkModel;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizModel;
use Schoolbox\Learning\Assessment\Task\Model as TaskModel;
use Schoolbox\User;

/**
 * Dehydrator for IAssessable objects.
 */
class Dehydrator
{
    /**
     * Dehydrates assessment, for purpose of generating a diff.
     *
     * Expected general usage is to call this on `$assessment` before and
     * after it gets saved, and then use that diff in a notifier.
     *
     * @param IAssessable $assessment
     * @return array
     */
    public function dehydrateAssessment(IAssessable $assessment): array
    {
        $dehydrated = $this->internalDehydrateAssessment($assessment);

        // Project: recursively dehydrate each activity
        if ($assessment instanceof Project) {
            $activities = [];
            foreach ($assessment->getAssessables() as $activity) {
                $activities[$activity->getId()] = $this->dehydrateAssessment($activity);
            }
            $dehydrated += [
                'activities' => $activities,
            ];
        }

        // Dehydrate participants too
        $dehydrated['participants'] = [];
        foreach ($assessment->getAllParticipants() as $participant) {
            $dehydratedParticipant         = $this->internalDehydrateParticipant($participant);
            $dehydratedParticipant['plan'] = $this->internalDehydratePlan($participant->getPlan());
            $dehydratedParticipant['user'] = $this->internalDehydrateUser($participant->getUser());
            $dehydrated['participants'][$participant->getUser()->getId()] = $dehydratedParticipant;
        }

        return $dehydrated;
    }


    /**
     * Dehydrates participation plan, for purpose of generating a diff.
     *
     * Expected general usage is to call this on each plan in an assessment
     * before and after it gets saved, and then use that diff in a notifier.
     *
     * @param Plan $plan
     * @return array
     */
    public function dehydratePlan(Plan $plan): array
    {
        $dehydrated               = $this->internalDehydratePlan($plan);
        $dehydrated['assessment'] = $this->internalDehydrateAssessment($plan->getAssessable());

        // Dehydrate participants too
        foreach ($plan->getParticipants() as $participant) {
            $dehydratedParticipant         = $this->internalDehydrateParticipant($participant);
            $dehydratedParticipant['user'] = $this->internalDehydrateUser($participant->getUser());
            $dehydrated['participants'][$participant->getUser()->getId()] = $dehydratedParticipant;
        }

        return $dehydrated;
    }


    /**
     * Dehydrates assessment into a set of plans.
     *
     * @param IAssessable $assessment
     * @return array
     */
    public function dehydrateAssessmentIntoPlans(IAssessable $assessment): array
    {
        $dehydrated   = [];
        $participants = [];
        foreach ($assessment->getPlans() as $plan) {
            // Is this plan persisted yet?
            // Ignore it for the purpose of dehydration?
            $planId              = $plan->getId();
            $dehydrated[$planId] = $this->dehydratePlan($plan);
            if (\is_array($dehydrated[$planId]['participants'])) {
                $participants += $dehydrated[$planId]['participants'];
            }
        }

        // Copy participants into each plan
        foreach ($dehydrated as $planId => $dehydratedPlan) {
            $dehydrated[$planId]['participants'] = $participants;
        }

        return $dehydrated;
    }


    /**
     * Dehydrates assessment into a set of participants.
     *
     * @todo add tests
     *
     * @param IAssessable $assessment
     * @return array
     */
    public function dehydrateAssessmentIntoParticipants(IAssessable $assessment): array
    {
        $dehydrated = [];
        $dehydrated['assessment'] = $this->internalDehydrateAssessment($assessment);
        foreach ($assessment->getPlans() as $plan) {
            $dehydratedPlan = $this->internalDehydratePlan($plan);
            $dehydrated['plan'][$plan->getId()] = $dehydratedPlan;
            foreach ($plan->getParticipants() as $participant) {
                $dehydratedParticipant         = $this->internalDehydrateParticipant($participant);
                $dehydratedParticipant['user'] = $this->internalDehydrateUser($participant->getUser());
                $dehydrated['participants'][$participant->getUser()->getId()] = $dehydratedParticipant;
            }
        }
        return $dehydrated;
    }


    /**
     * Helper for dehydrating an assessment.
     *
     * @param IAssessable $assessment
     * @return array
     */
    private function internalDehydrateAssessment(IAssessable $assessment): array
    {
        // Generic fields
        $dehydrated = [
            'id'             => $assessment->getId(),
            'type'           => $assessment->getFamilyName(),
            'title'          => $assessment->getTitle(),
            'weight'         => $assessment->getWeight(),
            'markVisibility' => $assessment->getMarkVisibility(),
            'publishDate'    => $assessment->getPublishAt() && !$assessment->getPublishAt()->isNull()
                ? $assessment->getPublishAt()->getDatabase()
                : null,
            'openDate'       => $assessment->getOpenAt() && !$assessment->getOpenAt()->isNull()
                ? $assessment->getOpenAt()->getDatabase()
                : null,
            'dueDate'        => $assessment->getDueAt() && !$assessment->getDueAt()->isNull()
                ? $assessment->getDueAt()->getDatabase()
                : null,
            'closeDate'      => $assessment->getCloseAt() && !$assessment->getCloseAt()->isNull()
                ? $assessment->getCloseAt()->getDatabase()
                : null,
            'workType'       => $assessment->getWorkType()
                // TODO remove explicit type-coercion once model hydration is fixed SA-4539
                ? (int)$assessment->getWorkType()->getId()
                : null,
        ];

        // Due-work specific
        // TODO would be more polite to put in its own helper, but ram it in here for now
        if ($assessment instanceof DueWorkModel) {
            $dehydrated += [
                'acceptOnline'           => $assessment->getAcceptOnline(),
                'selfReviewability'      => $assessment->getSelfReviewability(),
                'similarityCheckEnabled' => $assessment->getSimilarityCheckEnabled()
            ];
        }

        // Quiz-specific
        // TODO figure out what's actually needed here
        if ($assessment instanceof QuizModel) {
            $dehydrated += [
                'duration'    => $assessment->getDuration(),
                'maxAttempts' => $assessment->getMaxAttempts(),
            ];
        }

        // Task-specific
        if ($assessment instanceof TaskModel) {
            $dehydrated += [
                'selfAssessable' => $assessment->getIsSelfAssessable(),
            ];
        }

        return $dehydrated;
    }


    /**
     * Helper for dehydrating a participant.
     *
     * @param Participant $participant
     * @return array
     */
    private function internalDehydrateParticipant(Participant $participant): array
    {
        return [
            'planId'      => $participant->getPlan()->getId(),
            'description' => $participant->getDescription(),
            'publishDate' => $participant->getPublishDate()->getDatabase(),
            'openDate'    => $participant->getOpenDate()->getDatabase(),
            'dueDate'     => $participant->getDueDate()->getDatabase(),
            'closeDate'   => $participant->getCloseDate()->getDatabase(),
            'excluded'    => $participant->isExcluded(),
        ];
    }


    /**
     * Helper for dehydrating a plan.
     *
     * @param Plan $plan
     * @return array
     */
    private function internalDehydratePlan(Plan $plan): array
    {
        return [
            'id'          => $plan->getId(),
            'name'        => $plan->getName(),
            'dynamic'     => $plan->getIsDynamic(),
            'publishDate' => $plan->getPublishDate() && !$plan->getPublishDate()->isNull()
                ? $plan->getPublishDate()->getDatabase()
                : null,
            'openDate'    => $plan->getOpenDate() && !$plan->getOpenDate()->isNull()
                ? $plan->getOpenDate()->getDatabase()
                : null,
            'dueDate'     => $plan->getDueDate() && !$plan->getDueDate()->isNull()
                ? $plan->getDueDate()->getDatabase()
                : null,
            'closeDate'   => $plan->getCloseDate() && !$plan->getCloseDate()->isNull()
                ? $plan->getCloseDate()->getDatabase()
                : null,
        ];
    }


    /**
     * Helper for dehydrating a user.
     *
     * @param User $user
     * @return array
     */
    private function internalDehydrateUser(User $user): array
    {
        return [
            'id'        => $user->getId(),
            'title'     => $user->getTitle(),
            'firstname' => $user->getFirstname(),
            'lastname'  => $user->getLastname(),
        ];
    }
}
