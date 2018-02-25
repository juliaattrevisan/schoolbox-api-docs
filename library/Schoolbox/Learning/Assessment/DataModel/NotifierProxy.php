<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel;

use Alaress\Diff\Differ;
use Schoolbox\Assessment\Participant\Mapper as ParticipantMapper;
use Schoolbox\Assessment\Participant\Plan\Mapper as PlanMapper;
use Schoolbox\Learning\Assessment\Dehydrator;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;

/**
 * Proxy class for manually triggering notifications of assessments.
 *
 * Usage:
 * ```
 * $assessment = new IAssessable();
 * $notifier   = new NotifierProxy($assessmentDataSource, $planMapper);
 * $before     = $notifier->dehydrate($assessment);
 * $assessmentDataSource->save($assessment);
 * $notifier->notify($assessment, $currentUser, $before);
 * ```
 */
class NotifierProxy
{
    /**
     * @var MapperProxy
     */
    private $assessmentDataSource;

    /**
     * @var PlanMapper
     */
    private $planMapper;

    /**
     * @var ParticipantMapper
     */
    private $participantMapper;

    /**
     * @var Dehydrator
     */
    private $dehydrator;

    /**
     * @var Differ
     */
    private $differ;

    /**
     * Constructor
     *
     * @param MapperProxy       $assessmentDataSource
     * @param PlanMapper        $planMapper
     * @param ParticipantMapper $participantMapper
     */
    public function __construct(
        MapperProxy $assessmentDataSource,
        PlanMapper $planMapper,
        ParticipantMapper $participantMapper
    ) {
        $this->assessmentDataSource = $assessmentDataSource;
        $this->planMapper           = $planMapper;
        $this->participantMapper    = $participantMapper;
        // Consider injecting these, but it's not necessary
        $this->dehydrator           = new Dehydrator();
        $this->differ               = new Differ();
    }

    /**
     * Dehydrate the assessment before update, so that an accurate diff can be
     * created.
     *
     * In order to generate an accurate diff so that only the appropriate
     * notifications get raised, this must be:
     * * called before `$assessment` is saved
     * * its result must be passed to `notify` as its `$before` parameter
     *
     * @param null|IAssessable $assessment
     * @return array
     */
    public function dehydrate(?IAssessable $assessment): array
    {
        return $assessment && $assessment->getId()
            ? $this->dehydrator->dehydrateAssessmentIntoPlans($assessment)
            : [];
    }

    /**
     * Send notifications for this assessment.
     *
     * @param IAssessable $assessment The assessment to notify about.
     *                                It should contain an entire complement of
     *                                participants, so that users whose
     *                                participation changes can be notified.
     * @param User        $effector   Who triggered the action
     * @param array       $before     What was the state of `$assessment`
     *                                before it was saved?
     */
    public function notify(IAssessable $assessment, User $effector, array $before): void
    {
        $action = empty($before) ? PlanMapper::METHOD_CREATE : PlanMapper::METHOD_UPDATE;
        $after  = $this->dehydrate($assessment);

        // Now, trigger notifiers
        // Participant object has little idea of whether assessment was created or updated,
        // so just show updated.
        // Will need to rework actual notifs so they're verb-agnostic (besides delete)
        $assessmentModel = $this->assessmentDataSource->translateToDataModel($assessment);

        foreach ($assessment->getPlans() as $plan) {
            $beforePlan = $before[$plan->getId()] ?? [];
            $afterPlan  = $after[$plan->getId()]  ?? [];

            // Finagle together a plan model with the minimum amount of info I
            // can get away with
            // TODO consider a translation layer
            $planModel = $this->planMapper->createModel($plan->getId());
            $planModel->setName($plan->getName())
                      ->setDescription($plan->getDescription())
                      ->setIsDynamic($plan->getIsDynamic())
                      ->setPublishDate($plan->getPublishDate())
                      ->setOpenDate($plan->getOpenDate())
                      ->setDueDate($plan->getDueDate())
                      ->setCloseDate($plan->getDueDate())
                      ->setAssessment($assessmentModel);
            // Set participants too
            foreach ($plan->getParticipants() as $participant) {
                $participantModel = $this->participantMapper->createModel($participant->getId())
                    ->setUser($participant->getUser())
                    ->setExcludedAt($participant->getExcludedAt());
                $planModel->getParticipants()->setOffset($participant->getUser()->getId(), $participantModel);
            }

            $this->planMapper->getEventPropagator()->propagate(
                $effector,
                $action,
                $planModel,
                $this->planMapper,
                $this->differ->diff($afterPlan, $beforePlan)
            );
        }
    }
}
