<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IFeedback;

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
     * Compile a json representation for readonly grids, marked grids and self-graded grids.
     *
     * These are disparate pieces of data spread across multiple data structs so this is probably the most convenient
     * interface to use.
     *
     * ->generate(
     *     $assessment->getRubricInstances(), // ->getGrid() used later
     *     $feedback,                         // ->getRubricMarkedGrids() used later
     *     $selfAssessmentFeedback            // ->getRubricMarkedGrids() used later
     * );
     *
     * @param GenericCollection|null $rubricInstances Each rubric instance has a getGrid() mechanism
     * @param IFeedback|null $authoritativeFeedback Used to obtain marked grids from an instructor-published feedback
     * @param IFeedback|null $selfGradedFeedback Used to obtain arked grids from a learner-published feedback
     * @param bool $rubricShowPoints
     * @return array
     */
    public function generate(
        ?GenericCollection $rubricInstances,
        ?IFeedback $authoritativeFeedback,
        ?IFeedback $selfGradedFeedback,
        bool $rubricShowPoints,
    ): array {
        $struct = [];



        return $struct;
    }
}
