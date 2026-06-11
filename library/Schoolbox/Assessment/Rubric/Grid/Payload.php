<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Mapper\Exception;

/**
 * Compiles a frontend-friendly json-based struct consisting of:
 *    - all the rubric grid contents for an assessment
 *    - all the marked rubric grid contents for an assessment
 *    - all the self graded rubric grid contents for an assessment
 *
 * ... all in one big set. There's a config var, rubric_show_points, that is needed here too
 * as it determines whether the descriptors' numeric values are shown to a user.
 */
class Payload
{
    /**
     * @param RubricDataSource $rubricMapper
     * @param bool $rubricShowPoints
     */
    public function __construct(private RubricDataSource $rubricMapper, private bool $rubricShowPoints) {
    }


    /**
     * Compile a json representation for readonly grids, marked grids and self-graded grids.
     *
     * These are disparate pieces of data spread across multiple data structs so this is probably the most convenient
     * interface to use.
     *
     * ->generate(
     *     $assessment->getRubricInstances(), // ->getGrid() used later
     *     $selfAssessmentFeedback            // ->getRubricMarkedGrids() used later
     *     $feedback,                         // ->getRubricMarkedGrids() used later
     * );
     *
     * TODO There isn't a dedicated const for rubric_show_points lol...I guess I have to add that l8r
     *
     * @param GenericCollection|null $rubricInstances Each rubric instance has a getGrid() mechanism
     * @param IFeedback|null $selfGradedFeedback Used to obtain arked grids from a learner-published feedback
     * @param IFeedback|null $authoritativeFeedback Used to obtain marked grids from an instructor-published feedback
     * @return string
     * @throws Exception
     */
    public function generate(
        ?GenericCollection $rubricInstances,
        ?IFeedback $selfGradedFeedback,
        ?IFeedback $authoritativeFeedback,
    ): string {
        $struct = [];

        /** @var \Schoolbox\Assessment\Rubric\Instance\Model $rubricInstance */
        foreach ($rubricInstances as $rubricInstance) {
            $grid = $rubricInstance->getGrid()->toArray($this->rubricShowPoints);
            $selfGradedGrid = null;
            $markedGrid = null;

            /* Self-graded feedback is optional here too
             */
            if (null !== $selfGradedFeedback) {
                $selfGradedGrid = $this->rubricMapper
                    ->retrieveMarkedGrid($selfGradedFeedback, $rubricInstance)
                    ->getGrid()
                    ->toArray($this->rubricShowPoints);
            }

            /* Feedback is optional here
             */
            if (null !== $authoritativeFeedback) {
                $markedGrid = $this->rubricMapper
                    ->retrieveMarkedGrid($authoritativeFeedback, $rubricInstance)
                    ->getGrid()
                    ->toArray($this->rubricShowPoints);
            }

            $struct[] = [
                'rubric' => $grid,
                'selfGradedGrid' => $selfGradedGrid,
                'markedGrid' => $markedGrid,
            ];
        }

        return json_encode($struct);
    }
}
