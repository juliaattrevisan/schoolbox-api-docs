<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel;

use Alaress\Diff\Differ;
use Schoolbox\Assessment\Participant\Plan\Mapper as PlanMapper;
use Schoolbox\Learning\Assessment\Dehydrator;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;

/**
 * Class NotifierProxy
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
     * @param MapperProxy $assessmentDataSource
     * @param PlanMapper  $planMapper
     */
    public function __construct(MapperProxy $assessmentDataSource, PlanMapper $planMapper)
    {
        $this->assessmentDataSource = $assessmentDataSource;
        $this->planMapper           = $planMapper;
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
     * Notify the
     *
     * @param IAssessable $assessment
     * @param User        $effector
     * @param array       $before
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function notify(IAssessable $assessment, User $effector, array $before): void
    {
        // Retrieve assessment, as I need the assessment's participations
        $assessment = $this->assessmentDataSource->findByIdForReview($assessment->getId());
        $action     = empty($before) ? PlanMapper::METHOD_CREATE : PlanMapper::METHOD_UPDATE;
        $after      = $this->dehydrate($assessment);

        // Plans must come from the plan mapper

        // Now, trigger notifiers
        // Participant object has little idea of whether assessment was created or updated,
        // so just show updated.
        // Will need to rework actual notifs so they're verb-agnostic (besides delete)
        foreach ($assessment->getPlans() as $plan) {
            // I have NFI how I'm going to generate a diff for this item.
            // Maybe I just need to keep the old sub box notifier around.
            $beforePlan = $before[$plan->getId()] ?? [];
            $afterPlan  = $after[$plan->getId()]  ?? [];
            $this->planMapper->getEventPropagator()->propagate(
                $effector,
                $action,
                $plan,
                $this->planMapper,
                $this->differ->diff($afterPlan, $beforePlan)
            );
        }
    }
}
