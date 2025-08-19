<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Participant;

use DomainException;
use Schoolbox\Assessment\Participant\Model as ParticipantModel;
use Schoolbox\Assessment\Participant\Plan\Model as PlanModel;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentDataModel;
use Schoolbox\Learning\Assessment\Plan;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;

class ParticipationHelper
{
    /**
     * @var Collection<GenericCollection>|null $existingParticipationPerUser
     */
    private ?Collection $existingParticipationPerUser = null;

    public function __construct(
        private AssessmentDataModel $assessment,
        private DataSource $participantDataSource,
        private UserMapper $userMapper,
        private MapperProxy $mapperProxy,
    ) {
    }

    /**
     * Gets existing participations, indexed using (user id, plan id) tuples
     *
     * @param int $userId
     * @return ParticipantModel|null
     * @throws DomainException
     * @throws DefinitionException
     * @throws \PDOException
     */
    private function getExistingParticipation(int $userId): ?ParticipantModel
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
            $planIds = $participantMapper->findAssignedPlans($this->assessment->getId());

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
     * @param int            $userId
     * @param PlanModel|null $userPlan
     * @return void
     * @throws MapperException
     */
    public function exclude(int $userId, ?PlanModel $userPlan): void
    {
        $participantMapper = $this->participantDataSource->getParticipantMapper();
        /** @var ParticipantModel|null $existingParticipation */
        $existingParticipation = $this->getExistingParticipation($userId);

        if (!$userPlan) {
            $userPlan = $this->getDynamicPlan();
        }

        // excluded
        if (
            $existingParticipation
            && $existingParticipation->isExcluded()
            && $existingParticipation->getPlan()->getId() === $userPlan->getId()
        ) {
            // nothing changed
            return;
        }
        if ($existingParticipation) {
            if (!$existingParticipation->isExcluded()) {
                $participantMapper->softDeleteResponsesAndFeedbacks($existingParticipation, $this->assessment);
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
     * @param int            $userId
     * @param PlanModel|null $userPlan
     * @return void
     * @throws MapperException
     */
    public function include(int $userId, ?PlanModel $userPlan): void
    {
        $participantMapper = $this->participantDataSource->getParticipantMapper();
        /** @var ParticipantModel|null $existingParticipation */
        $existingParticipation = $this->getExistingParticipation($userId);
        if (!$userPlan) {
            $userPlan = $this->getDynamicPlan();
        }

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
            $keepExistingParticipation = false;

            if ($existingParticipation->isExcluded()) {
                $restoredCount = $participantMapper->restoreResponsesAndFeedbacks($existingParticipation, $this->assessment);
                // if new plan is dynamic and if recovered responses and feedbacks exist, keep the participant data
                if ($userPlan->isDynamic() && $restoredCount > 0) {
                    $existingParticipation->setExcludedAt(null);
                    $participantMapper->save($existingParticipation);
                    $keepExistingParticipation = true;
                }

                $customisation = $this->mapperProxy->findAllCustomisedAssessment($this->mapperProxy->translateToDomainModel($this->assessment));
                $test = $customisation->first()?->getParticipantIfExtant($this->userMapper->createModel($userId));
                if (null !== $test) {
                    $test->setExcludedAt(\Alaress_Date_Format::now());
                    $participantMapper->save($test);
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
     * @return PlanModel
     * @throws MapperException
     */
    private function getDynamicPlan(): PlanModel
    {
        $planMapper = $this->participantDataSource->getPlanMapper();
        return $planMapper->findOneByColumnList(['assessment_id' => $this->assessment->getId(), 'is_dynamic' => true]);
    }
}
