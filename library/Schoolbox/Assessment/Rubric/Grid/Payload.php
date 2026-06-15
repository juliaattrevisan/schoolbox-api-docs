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
use Schoolbox\User;

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
     * @param User $viewer
     */
    public function __construct(
        private RubricDataSource $rubricMapper,
        private bool $rubricShowPoints,
        private \Schoolbox\User $viewer
    ) {
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

        /* Converts the grid structure
         */
        $rubricGridTranslator = new \Schoolbox\Assessment\Rubric\Grid\Translator\ModelToArray\Base($this->rubricShowPoints);

        /* TODO: similar to Grid->jsonSerialize, this is not final and will be easier to figure out what needs
         *  to be changed once we can see all the payloads for the builder/readonly/markable grids in action.
         *  There's also the assessment API to consider, i'm ignoring it for now as we'll need to change it regardless to accommodate multi rubrics
         *  To avoid passing thru a translator down very deep twig file chains in a lot of places, we can do it here for now
         */

        /* TODO: ensure access controls (particularly whether students can see mark info assuming there's cases when they shouldn't?) are handled correctly;
         *  there are more notes both in this translator and in the Grid translators about addressing this.
         */
        $rubricMarkedGridTranslator = new \Schoolbox\Assessment\Rubric\MarkedGrid\Translator\ModelToArray(
            $this->viewer,
            $this->rubricShowPoints,
        );

        /** @var \Schoolbox\Assessment\Rubric\Instance\Model $rubricInstance */
        foreach ($rubricInstances as $rubricInstance) {
            $grid = $rubricGridTranslator($rubricInstance->getGrid());
            $selfGradedGrid = null;
            $markedGrid = null;

            /* Self-graded feedback is optional here too
             */
            if (null !== $selfGradedFeedback) {
                $selfGradedGrid = $rubricMarkedGridTranslator($this->rubricMapper
                    ->retrieveMarkedGrid($selfGradedFeedback, $rubricInstance));
            }

            /* Feedback is optional here
             */
            if (null !== $authoritativeFeedback) {
                $markedGrid = $rubricMarkedGridTranslator($this->rubricMapper
                    ->retrieveMarkedGrid($authoritativeFeedback, $rubricInstance));
            }

            $struct[] = [
                'rubric' => $grid,
                'selfAssessmentFeedback' => $selfGradedGrid,
                'markedGrid' => $markedGrid,
            ];
        }
new \Alaress_Probe($struct);
        return json_encode($struct);
    }
}
