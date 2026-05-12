<?php

namespace Schoolbox\Assessment\Rubric;

use Alaress\Json\Decorator\LinkDecorator;
use Alaress\Json\Decorator\PropertyDecorator;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Rubric\Grid\Factory as GridFactory;
use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\Assessment\Rubric\RowSet\RowSet;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\ISelfAssessable;
use Schoolbox\Mapper\Exception;


class ValidGridSet
{
    /**
     * A shortcut to initial conditions
     */
    public const array DEFAULT_RESPONSE = [
        'compatible' => true,
        'affectedUsers' => [],
    ];


    /**
     * This is an abstracted refactor of determineMarkInvalidation() from the rubric controller.
     */
    public function __construct(
        private LinkDecorator $linkDecorator,
        private RubricDataSource $rubricMapper,
        private \Schoolbox\Assessment\Rubric\Instance\Mapper $rubricInstanceMapper,
        private MarkTypeMapper $markTypeMapper
    ) {
    }


    /**
     * Determines if $post grids were compatible with each of those known to $assessment.
     *
     * If one grid is invalid, all are invalid: it requires feedback to be re-evaluated.
     *
     * Blank grids are used to compare against existing grids in the case where a grid was deleted from the frontend but
     * we needed to see if it was going to cause marks to become invalid due to previous usage of it in a feedback.
     *
     * @param array $post An array of (rubric id, json data) tuples corresponding to grid configurations
     * @param IAssessable $currentAssessment A fully hydrated learning activity
     * @return array
     * @throws Exception
     */
    public function isValid(array $post, IAssessable $currentAssessment): array
    {
        $response = self::DEFAULT_RESPONSE;

        /* In 26.1, any activity could have N number of rubric grids associated with it. A change in any of them would
         * mean that any previously-received feedback would be obsoleted.
         */
        // Load the proposed assessment and grid
        // Need to load them separately due to object references: if I use the
        // same assessment in both retrieveGrid calls the current grid thinks
        // the current assessment state is the same as the proposed state.
        $proposedAssessment = (clone $currentAssessment);

        /* Stuff we'll need later; instead of respawning them in every iteration, just spawn them once.
         */
        $gridFactory = new GridFactory();
        $jsonDecorator = new PropertyDecorator();
        $linkDecorator = $this->linkDecorator;

        /* foreach rubric instance, check if it's compatible with the proposed grid.
         *
         * If it is *not* compatible, $response['compatible'] can be set to false then return early...I don't *think*
         * we care about *which grid(s)* caused the invalidation of a feedback; teacher needs to go back and re-mark
         * all of them anyway.
         *
         * I spoke to jeztek about it, and we'll start with something simple with a mind to add nuance to it (and the
         * corresponding modal) later, as required.
         */
        /** @var \Schoolbox\Assessment\Rubric\Instance\Model $rubricInstance */
        foreach ($currentAssessment->getRubricInstances() as $rubricInstance) {
            $currentGrid = $this->rubricMapper->retrieveOneGrid($currentAssessment, $rubricInstance, false);

            /* This should knowingly throw an exception if you couldn't decode it. This normally shouldn't be the case.
             * I'm not entirely sure how we'd construct an exception-throwing payload though. Arguably this should throw
             * on error: I can't tell why it didn't when I got here.
             *
             * Grid deletion:
             * Anyway, if a $rubricData didn't exist, it's probably because $rubricInstance USED to exist, but has been
             * deleted from the frontend and thus doesn't manifest in the $post payload. That means we need to construct
             * a totally empty grid, no caps/inds/descs and kind of trick the isCompatible methods into returning false.
             * This basically equates to "if a grid that was in use and had marks, but it's been deleted now, then we
             * need to invalidate feedback."
             */
            if (!isset($post[$rubricInstance->getId()])) {
                /* As empty as empty can be
                 */
                $proposedGrid = new Grid(
                    $currentAssessment,
                    $this->rubricInstanceMapper->createModel(),
                    new RowSet($currentAssessment->getContext())
                );
            } else {
                $rubricData = json_decode($post[$rubricInstance->getId()], true);

                /* Manufacture new capability mark type and mark range settings, in situ
                 *
                 * These become ingredients for the grid we intend to make so we need to ensure they're split (and distinct)
                 * from whence they came, in $currentAssessment's rubric instance. The grid should be standalone.
                 */
                $capabilityMarkTypeSlug = $rubricInstance->getCapabilityMarkType()->getSlug() ?? MarkType::SLUG_NONE;

                $capabilityMarkType = $this->markTypeMapper->createModel()
                    ->setSlug($capabilityMarkTypeSlug)
                    ->setId(MarkType::slugToId($capabilityMarkTypeSlug));

                $useMarkRange = $rubricInstance->getMarkedUsingRange() ?? false;

                /* Assemble a rubric instance that can be used to compare grids before and after.
                 * We don't need to add $proposedRubricInstance to $proposedAssessment's rubric instance collection atm.
                 *
                 * The original rubric instance id is needed to find the json-encoded $_POST['rubric'][$rubricInstanceId]
                 * node, so we have to supply it here, even though $proposedRubricInstance is a whole new spawn to prevent
                 * object-by-reference conflicts. Wild.
                 */
                $proposedRubricInstance = $this->rubricInstanceMapper->createModel()
                    ->setId($rubricInstance->getId())
                    ->setCapabilityMarkType($capabilityMarkType)
                    ->setMarkedUsingRange($useMarkRange);

                $proposedGrid = $gridFactory->buildFromPostData(
                    $this->rubricMapper->retrieveOneGrid($proposedAssessment, $proposedRubricInstance, false),
                    $rubricData,
                    false
                );
            }


            // Is the proposed grid itself not valid?
            // Doesn't matter for the purpose of this exercise
            if (!$proposedGrid->isCompatibleWith($currentGrid)) {
                $response['compatible'] = false;

                // Determine who's actually affected?
                foreach ($currentAssessment->getAllParticipants() as $participant) {
                    $user   = $participant->getUser();
                    $userId = $user->getId();

                    // Instructor-given feedback
                    if ($this->rubricMapper->hasInstructorMarkedGrid($participant)) {
                        $response['affectedUsers'][$userId]['instructorGiven'] = true;
                    }

                    // Self-assessment feedback
                    if ($currentAssessment instanceof ISelfAssessable && $currentAssessment->isSelfAssessable() && $this->rubricMapper->hasSelfAssessedMarkedGrid($participant)) {
                        $response['affectedUsers'][$userId]['selfAssess'] = true;
                    }

                    // If either type of feedback set: set the user as affected
                    if (!empty($response['affectedUsers'][$userId])) {
                        $response['affectedUsers'][$userId]['user'] = $user->jsonSerialize($jsonDecorator, $linkDecorator);
                    }
                }

                /* Exit early if any of the rubric grids are now different, causing someone's entire feedback to require
                 * re-marking; maybe later we can revise how this modal communicates *which grid* is invalid instead
                 */
                // Response code is always 200:
                // this is a valid request even if marks would've been invalidated.
                return $response;
            }
        }

        /* I guess everything is valid
         */
        return $response;
    }
}
