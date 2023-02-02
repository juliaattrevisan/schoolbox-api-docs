<?php

namespace Schoolbox\Learning\Assessment\Participant;

use DomainException;
use Schoolbox\Assessment\Participant\Model as ParticipantModel;
use Schoolbox\Assessment\Participant\Plan\Model;
use Schoolbox\Assessment\Participant\Plan\Model as PlanModel;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;

class ParticipationHelper
{
    /**
     * @var Collection<GenericCollection>|null $existingParticipationPerUser
     */
    private $existingParticipationPerUser;

    public function __construct(private DataSource $participantDataSource, private UserMapper $userMapper)
    {
    }

    /**
     * @param GenericCollection|PlanModel[] $plans
     * @param array                         $inclusionFlags  userId => 0 ... excluded, 1 ... included
     * @param array                         $userPlanIndices userId => planId
     * @throws MapperException
     */
    public function saveParticipationData(IAssessable $assessment, GenericCollection $plans, array $inclusionFlags, array $userPlanIndices): void
    {
        $participantMapper = $this->participantDataSource->getParticipantMapper();

        // plans are saved; persistipation
        foreach ($inclusionFlags as $userId => $userIncluded) {
            /** @var ParticipantModel|null $existingParticipation */
            $existingParticipation = $this->getExistingParticipation($assessment, $userId);
            /** @var PlanModel $userPlan */
            $userPlan = $plans[$userPlanIndices[$userId]];
            if ($userIncluded) {
                if (!$userPlan) {
                    throw new DomainException("User {$userId} was assigned index {$userPlanIndices[$userId]}, but there's no plan with that index (valid plan indices were " . json_encode($plans->keys()) . ')');
                }

                $this->include($assessment, $userId, $userPlan);
            } else {
                $this->exclude($assessment, $userId, $userPlan);
            }
        }
    }
    /**
     * Gets existing participations, indexed using (user id, plan id) tuples
     *
     * @param IAssessable       $assessment
     * @param int               $userId
     * @return ParticipantModel|null
     * @throws DomainException
     * @throws DefinitionException
     * @throws \PDOException
     */
    private function getExistingParticipation(IAssessable $assessment, int $userId): ?ParticipantModel
    {
        // If haven't got participation data, get it
        if (!$this->existingParticipationPerUser) {
            $participantMapper = $this->participantDataSource->getParticipantMapper();

            /* For each of those assigned plans, find out if $userId is associated with it
             * If it is, then this person already has a plan - index them with a
             * (user id, plan id) tuple. This is the actual truth about which user
             * has a plan in a given assessment; you cannot trust the GenericCollection
             * $plans to contain the source of truth
             */
            $planIds = $participantMapper->findAssignedPlans($assessment->getId());

            /* Shank it into the same struct so we can find out which plan
             * was given to a particular user
             */
            $allExistingParticipation = $participantMapper->findByColumnList([
                                                                                 'participant_plan_id' => $planIds,
                                                                             ], ['cache' => false]);

            // userId => plan (should only have one plan per user)
            $this->existingParticipationPerUser = $allExistingParticipation->groupByCallback(
                function (ParticipantModel $model) {
                    return $model->getUser()->getId();
                }
            );
        }

        // Check to see whether only one participation exists for the current user and get it
        if ($this->existingParticipationPerUser->offsetExists($userId)) {
            /** @var GenericCollection $existingParticipationCollection */
            $existingParticipationCollection = $this->existingParticipationPerUser->getOffset($userId);
            if ($existingParticipationCollection->count() > 1) {
                throw new DomainException('More than one existing participation found for an user');
            }
            if ($existingParticipationCollection->count() === 1) {
                return $existingParticipationCollection->first();
            }
        }
        return null;
    }

    /**
     * @param IAssessable $assessment
     * @param int         $userId
     * @return void
     * @throws MapperException
     */
    public function exclude(IAssessable $assessment, int $userId, PlanModel|null $plan = null): void
    {
        $participantMapper = $this->participantDataSource->getParticipantMapper();
        /** @var ParticipantModel|null $existingParticipation */
        $existingParticipation = $this->getExistingParticipation($assessment, $userId);
        /** @var PlanModel $userPlan */
        $userPlan = $plan ?: $this->getPlans($assessment->getId())[$assessment->getId()];

        // excluded
        if ($existingParticipation && $existingParticipation->isExcluded() && $existingParticipation->getPlan()->getId() === $userPlan->getId()) {
            // nothing changed
            return;
        }
        if ($existingParticipation) {
            if (!$existingParticipation->isExcluded()) {
                $participantMapper->softDeleteResponsesAndFeedbacks($existingParticipation);
            }
            $participantMapper->delete($existingParticipation);
        }

        // we need to generate a record so we have *something* to exclude
        $newParticipation = $participantMapper->createModel();
        $newParticipation->setUser($this->userMapper->createModel($userId))
                         ->setPlan($userPlan)
                         ->setExcludedAt(\Alaress_Date_Format::now());
        $participantMapper->save($newParticipation);
    }

    /**
     * @param IAssessable $assessment
     * @param int         $userId
     * @return void
     * @throws MapperException
     */
    public function include(IAssessable $assessment, int $userId, PlanModel|null $plan = null): void
    {
        $participantMapper = $this->participantDataSource->getParticipantMapper();
        /** @var ParticipantModel|null $existingParticipation */
        $existingParticipation = $this->getExistingParticipation($assessment, $userId);
        /** @var PlanModel $userPlan */
        $userPlan = $plan ?: $this->getPlans($assessment->getId())[$assessment->getId()];

        // generate a record if there is ALREADY existing participation, OR the plan is NOT dynamic
        if (!$existingParticipation && $userPlan->isDynamic()) {
            return;
        }

        if ($existingParticipation && !$existingParticipation->isExcluded() && ($existingParticipation->getPlan()->getId() === $userPlan->getId())) {
            // nothing changed
            return;
        }

        // delete existing participation
        if ($existingParticipation) {
            $restoredCount             = 0;
            $keepExistingParticipation = false;

            if ($existingParticipation->isExcluded()) {
                $restoredCount = $participantMapper->restoreResponsesAndFeedbacks($existingParticipation);
                // if new plan is dynamic and if recovered responses and feedbacks exist, keep the participant data
                if ($userPlan->isDynamic() && $restoredCount > 0) {
                    $existingParticipation->setExcludedAt(null);
                    $participantMapper->save($existingParticipation);
                    $keepExistingParticipation = true;
                }
            }

            if (!$keepExistingParticipation) {
                $participantMapper->delete($existingParticipation);
            }
        }

        if (!$userPlan->isDynamic()) {
            // we need to generate a record so we have *something* to exclude
            $newParticipation = $participantMapper->createModel();
            $newParticipation->setUser($this->userMapper->createModel($userId))
                             ->setPlan($userPlan);
            $participantMapper->save($newParticipation);
        }
    }

    /**
     * @param int $assessmentId
     * @return GenericCollection
     */
    private function getPlans(int $assessmentId): GenericCollection
    {
        $planMap = $this->participantDataSource->getPlanMapper();

        // preload the plans list with the dyna-plan
        return $planMap->findByColumnList(['assessment_id' => $assessmentId ])->indexByCallback(function (Model $model) {
            return $model->getAssessment()->getId();
        });
    }
}
