<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace External\Canvas\Sync\Bridge;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;

/**
 * Encapsulates the mapping between a Canvas AssignmentOverride and Schoolbox ParticipationPlan
 */
class AssignmentOverride
{

    /** @var IConnector */
    private $schoolboxDb;


    /**
     * @param IConnector $schoolboxDb
     */
    public function __construct(IConnector $schoolboxDb)
    {
        $this->schoolboxDb = $schoolboxDb;
    }


    /**
     * Returns a consistent string fragment that will be prefixed to the canvasId to identify a SB Plan
     *
     * @return string
     */
    private static function canvasIdPrefix(): string
    {
        return 'canvas-override';
    }


    /**
     * In Canvas, if a assignment is for 'everyone', the date attributes are stored on the
     * assignment, rather than an override.
     * But Schoolbox requires a dynamic plan in this case so that class members can see the assessment.
     *
     * We will generate a fake override in this case, so we can more easily map to a Schoolbox plan.
     *
     * This method returns the value we give to the fake override's title.
     *
     * @return string
     */
    public function getEveryoneOverrideTitle(): string
    {
        return 'everyone';
    }


    /**
     * Returns a consistent value we will give to Plans that are created to represent the 'everyone' assignment attribute.
     * This allows us to more easily distinguish these plans, from dynamic plans not related to the Canvas import.
     *
     * Note that this 'canvasId', unlikely real ones, is not globally unique, but is only unique in the context of a given assessment.
     *
     * @return int
     */
    public function getEveryoneDynamicPlanCanvasId(): int
    {
        return 0;
    }


    /**
     * Return a predicate that can find a Schoolbox Plan for the specified AssignmentOverride
     *
     * We also specify the assessment, to ensure the 'everyone' plan can be uniquely identified.
     * This implies we don't expect an override can be moved to a different assignment.
     *
     * @param int         $overrideCanvasId
     * @param int         $assessmentImplId
     * @param string|null $planTable
     * @return Builder
     * @throws InvalidColumnException
     */
    public function equalsCanvasOverride(int $overrideCanvasId, int $assessmentImplId, string $planTable): Builder
    {
        $pb = (new Builder($this->schoolboxDb, ''))
            ->regex("{$planTable}.name", "^.+\\({$this::canvasIdPrefix()}-{$overrideCanvasId}\\)$")
            ->equals("{$planTable}.assessment_id", $assessmentImplId);
        return (new Builder($this->schoolboxDb, ''))->all($pb);
    }


    /**
     * Returns a predicate that will match any Schoolbox plan that was imported from Canvas
     *
     * @param string|null $planTable
     * @return Builder
     * @throws InvalidColumnException
     */
    public function equalsAnyCanvasOverride(string $planTable): Builder
    {
        return (new Builder($this->schoolboxDb, ''))->regex("{$planTable}.name", "^.+\\({$this::canvasIdPrefix()}-\\d+\\)$");
    }


    /**
     * Consistently generate a Schoolbox plan name for a Canvas Assignment Override
     *
     * @param string $overrideTitle
     * @param int    $overrideCanvasId
     * @return string
     */
    public function generateLinkedPlanName(string $overrideTitle, int $overrideCanvasId): string
    {
        return "$overrideTitle ({$this::canvasIdPrefix()}-{$overrideCanvasId})";
    }
}
