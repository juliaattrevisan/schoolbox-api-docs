<?php
/**
 * RubricContext
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\DueWork;

use Schoolbox\Assessment\Component\SubmissionBox\Rubric\GridGenerator;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\User;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;

class RubricContext
{

    /**
     * @var RubricMapper
     */
    private $rubricMapper;

    /**
     * @param RubricMapper $rubricMapper
     */
    public function __construct(RubricMapper $rubricMapper)
    {
        $this->rubricMapper = $rubricMapper;
    }

    /**
     * Get a markable grid for Rubrics marking, for an Instructor and particular IResponse
     *
     * @param Model $dueWork
     * @param User  $student
     * @param int   $responseId
     *
     * @return array
     */
    public function generateMarkableForResponseAndSelfReflection(DueWork $dueWork, User $student, int $responseId)
    {
        if (!$dueWork->getUseRubric()) {
            return ['rubric' => null];
        }

        $context = $this->generateDefaultContext($dueWork);

        /* Spawn rubric grid
         */
        (new GridGenerator($this->rubricMapper, $dueWork->getRubric(), $student))->applySelfAssessedMarkForResponse(false, $responseId);
        $context['rubric'] = $dueWork->getRubric()->toContext();

        return $context;
    }

    /**
     * Get a markable grid for Rubrics marking, for an Instructor and particular IResponse
     *
     * @param Model $dueWork
     * @param User  $student
     * @param int   $responseId
     *
     * @return array
     */
    public function generateMarkableForResponse(DueWork $dueWork, User $student, int $responseId)
    {
        if (!$dueWork->getUseRubric()) {
            return ['rubric' => null];
        }

        $context = $this->generateDefaultContext($dueWork);

        /* Spawn rubric grid
         */
        (new GridGenerator($this->rubricMapper, $dueWork->getRubric(), $student))->applyInstructorGivenMarkForResponse(false, $responseId);
        $context['rubric'] = $dueWork->getRubric()->toContext();

        return $context;
    }

    /**
     * Get a markable grid for Rubrics marking, for an Instructor
     *
     * @param DueWork  $dueWork
     * @param User     $student
     * @param bool     $publishedOnly Whether to exclude draft marks
     *
     * @return array
     */
    public function generateMarkable(DueWork $dueWork, User $student, bool $publishedOnly = true)
    {
        if (!$dueWork->getUseRubric()) {
            return ['rubric' => null];
        }

        $context = $this->generateDefaultContext($dueWork);

        /* Spawn rubric grid
         */
        (new GridGenerator($this->rubricMapper, $dueWork->getRubric(), $student))->applyInstructorGivenMark($publishedOnly);
        $rubric = $dueWork->getRubric()->toContext();
        $context['rubric'] = array_merge($rubric, ['descriptorMetadata' => $this->generateRubricMarkMetadata($rubric)]);

        return $context;
    }


    /**
     * Generate default $context array structure
     *
     * @param Model $dueWork
     *
     * @return array
     */
    private function generateDefaultContext(DueWork $dueWork)
    {

        /* Inject available levels for this DueWork's GradeGroup
         */
        $gradeLevels = [];

        if ($dueWork->getGradeGroup()) {
            foreach ($dueWork->getGradeGroup()->getGradeLevels() as $gradeLevel) {
                $gradeLevels[$gradeLevel->getLowerBound()] = $gradeLevel->getCaption();
            }
        }

        $context = [
            'markType' => [
                'mode' => $dueWork->getMarkType()->getSlug(),
                'conf' => $gradeLevels,
            ],
        ];

        return $context;
    }

    /**
     * Get a readonly grid and the student's current published rubric values
     *
     * @param DueWork $dueWork
     * @param User    $student
     * @param User    $currentUser
     * @param Access  $access
     * @param bool    $publishedOnly
     *
     * @return array
     */
    public function generate(DueWork $dueWork, User $student, User $currentUser, Access $access, $publishedOnly = true)
    {
        return $this->generateReadonlyInternal($dueWork, $student, $currentUser, $access, $publishedOnly);
    }

    /**
     * Get a student agnostic readonly grid
     *
     * @param DueWork $dueWork
     *
     * @return array
     */
    public function generateBasic(DueWork $dueWork, User $currentUser)
    {
        if (!$dueWork->getUseRubric()) {
            return ['rubric' => null];
        }

        $rubric = $dueWork->getRubric()->toContext($currentUser);
        $context = [
            'rubric' => array_merge($rubric, ['descriptorMetadata' => $this->generateRubricMarkMetadata($rubric)])
        ];

        // Don't show total mark here at all (it's zero anyway when there is no Participant)
        unset($context['rubric']['total']);

        return $context;
    }

    /**
     * Get a readonly grid for a particular feedback.
     *
     * @param DueWorkFeedback $feedback
     * @param User            $currentUser
     * @param Access          $access
     * @param bool            $publishedOnly
     * @return array
     */
    public function generateForFeedback(DueWorkFeedback $feedback, User $currentUser, Access $access, $publishedOnly = true)
    {
        $dueWork = $feedback->getAssessable();
        if (!$dueWork instanceof DueWork) {
            return ['rubric' => null];
        }
        $rubric = $this->generateReadonlyInternal(
            $dueWork,
            $feedback->getReviewee(),
            $currentUser,
            $access,
            $publishedOnly,
            $feedback->getId()
        );
        // Post-process: if self-reflect, remove descriptor values
        if ($feedback->isSelfReflectionOnly()) {
            foreach ($rubric['rubric']['descriptors'] ?? [] as $indicatorId => $descriptors) {
                foreach ($descriptors as $level => $descriptor) {
                    $rubric['rubric']['descriptors'][$indicatorId][$level]['value'] = null;
                }
            }
        }
        return $rubric;
    }

    /**
     * Internal method for generating a readonly rubric
     *
     * @param Model  $dueWork
     * @param User   $student
     * @param User   $currentUser
     * @param Access $access
     * @param bool   $publishedOnly
     * @param null   $feedbackId
     * @return array
     */
    private function generateReadonlyInternal(
        DueWork $dueWork,
        User $student,
        User $currentUser,
        Access $access,
        $publishedOnly = true,
        $feedbackId = null
    ) {
        if (!$dueWork->getUseRubric()) {
            return ['rubric' => null];
        }

        // No feedback ID given: assume I wanted instructor-given mark
        $gridGenerator = new GridGenerator($this->rubricMapper, $dueWork->getRubric(), $student);
        if ($feedbackId) {
            $gridGenerator->applyMarkForFeedback($publishedOnly, $feedbackId);
        } else {
            $gridGenerator->applyInstructorGivenMark($publishedOnly);
        }

        $rubric = $dueWork->getRubric()->toContext($currentUser);
        $context = [
            'rubric' => array_merge($rubric, ['descriptorMetadata' => $this->generateRubricMarkMetadata($rubric)])
        ];

        // Hide total mark from students and parents
        $accessLevel = $access->getFolderAccess($currentUser, $dueWork->getBaseContext()->getFolder()->getId());
        if ($accessLevel < UserAccess::ACCESS_WRITE) {
            unset($context['rubric']['total']);
        }

        return $context;
    }

    /**
     * Generates an array of metadata about the values of descriptors in a rubric
     *
     * @param array $rubric     Rubric data structure return by Rubric::toContext()
     * @return array            Array indexed by criteria and level IDs, containing
     *                          - sort order for criteria
     *                          - max and min value for each level (descriptor) in the criteria
     */
    private function generateRubricMarkMetadata(array $rubric) : array
    {
        // Array of descriptors indexed by criteria and level ID
        $descriptors = $rubric['descriptors'];

        // Get a list of all levels in this rubric.
        // The descriptor array contains only the levels that have descriptors,
        // so we can't get this info on the fly when looping through descriptors.
        $levelIds = array_keys($rubric['levels']);

        return $this->generateCriterionMetadata($descriptors, $levelIds);
    }

    /**
     * Loop through criteria and determine
     * - the sorting direction of their descriptors
     * - metadata for each descriptor
     *
     * @param array $descriptors    Descriptors indexed by the level they belong to.
     * @param array $levelIds       List of IDs of all the levels in this rubric.
     * @return array
     */
    private function generateCriterionMetadata(array $descriptors, array $levelIds) : array
    {
        $metadata = [];

        // Indexes of the first and last levels that have descriptor values
        $firstValuedLevelIndex = 0;
        $lastValuedLevelIndex = \count($levelIds);

        foreach ($descriptors as $critId => $levelDescriptors) {
            // The IDs of the first and last level in the criterion
            $firstLevel = $levelIds[$firstValuedLevelIndex];
            $lastLevel = $levelIds[$lastValuedLevelIndex - 1];

            // Descriptor values of the first and last level; if no value is found, null
            $startValue = $descriptors[$critId][$firstLevel] ? $descriptors[$critId][$firstLevel]['value'] : null;
            $endValue = $descriptors[$critId][$lastLevel] ? $descriptors[$critId][$lastLevel]['value'] : null;

            // TODO this is working for the second group but not the first...
            // If the first or last descriptor doesn't have points, skip this iteration and adjust our idea of where the values start

            if ($startValue === null) {
                // If the first level has no points, try the next level...and so on
                $firstValuedLevelIndex++;
                continue;
            }
            if ($endValue === null) {
                // If the last level has no points, try the second-to-last...and so on
                $lastValuedLevelIndex--;
                continue;
            }

            // Assume ascending values by default
            $direction = 'asc';
            if ($startValue > $endValue) {
                $direction = 'desc';
            }

            $metadata[$critId] = [
                'direction' => $direction
            ];

            $this->generateDescriptorMetadata($metadata, $levelDescriptors, $lastValuedLevelIndex, $direction, $startValue, $endValue, $critId);
        }

        return $metadata;
    }

    /**
     * Loops through descriptors in a criterion and stores their min and max value in the $metadata array, indexed by $critId.
     *
     * @param array $metadata               Passed by reference, as we need to mutate this array.
     * @param array $levelDescriptors       Descriptors indexed by the level they belong to.
     * @param int $lastValuedLevelIndex     ID of the last level we know of that has a descriptor with a value.
     * @param string $direction             Ordering of the descriptor values - asc or desc
     * @param int $startValue               Value of the first descriptor with a value
     * @param int $endValue                 Value of the last descriptor with a value
     * @param int $critId                   ID of the criterion whose descriptors we are currently examining.
     */
    private function generateDescriptorMetadata(array &$metadata, array $levelDescriptors, int $lastValuedLevelIndex, string $direction, int $startValue, int $endValue, int $critId) : void
    {
        // At this point there is no "previous" cell yet since we're at the start, so assign the first cell's value
        $prevDescriptorMax = $startValue;

        for ($i = 0; $i < $lastValuedLevelIndex; $i++) {
            // If this level has descriptors, calculate the range
            if (array_key_exists($i, $levelDescriptors)) {

                // The cell's stored value will always be its maximum
                $currentDescriptorMax = $levelDescriptors[$i]['value'];

                // Skip this iteration if this descriptor has no value
                if ($currentDescriptorMax === null) {
                    continue;
                }

                /* Assume ascending order by default
                 * - If the value for this descriptor is the same as the previous cell, we're either on the first cell,
                 *   or on a cell without a range
                 * - Otherwise, this cell's min value is 1 greater than the previous cell's max, since we're ascending
                 */
                // TODO strict comparison should return true here, why isn't it
                $currentDescriptorMin = ($prevDescriptorMax == $currentDescriptorMax) ? $prevDescriptorMax : $prevDescriptorMax + 1;

                // If descending....
                if ($direction === 'desc') {
                    /* - If there is a 'next' cell, use its value
                     * - Otherwise just use the same value as this cell
                     */
                    if ($levelDescriptors[$i + 1] && ($levelDescriptors[$i + 1]['value'] !== null)) {
                        $nextDescriptorMax = $levelDescriptors[$i + 1]['value'];
                    } else {
                        $nextDescriptorMax = $endValue;
                    }

                    /* - If the value for this descriptor is the same as the next cell, we're either on the last cell,
                     *   or on a cell without a range
                     * - Otherwise, this cell's min value is 1 greater than the next cell's max, since we're descending
                     */
                    // TODO strict comparison should return true here, why isn't it
                    $currentDescriptorMin = ($currentDescriptorMax == $nextDescriptorMax) ? $nextDescriptorMax : $nextDescriptorMax + 1;
                }

                // Index min and max by level ID for this criterion
                $metadata[$critId][$i] = [
                    'min' => $currentDescriptorMin,
                    'max' => $currentDescriptorMax
                ];

                $prevDescriptorMax = $currentDescriptorMax;
            } else {
                // If this level has no descriptors, skip the iteration
                continue;
            }
        }
    }



    /**
     * Calls `$this->generateForFeedback` for each feedback in `$timeline`,
     * whether it belongs to a response or not.
     *
     * @param Collection $timeline      The timeline, from `\Schoolbox\Learning\Assessment\Timeline\Full::getFor*`
     * @param User       $currentUser   Current logged-in user
     * @param Access     $access        Access determination object
     * @param bool       $publishedOnly Get only published items?
     * @return Collection<int,array> key: feedback ID, value: result from `$this->generateForFeedback`
     */
    public function generateSeriesForTimeline(
        Collection $timeline,
        User $currentUser,
        Access $access,
        $publishedOnly = true
    ): Collection {
        // Collate all feedback in timeline
        $timelineFeedback = [];
        foreach ($timeline as $timelineItem) {
            if ($timelineItem instanceof Feedback\Model) {
                $timelineFeedback[] = $timelineItem;
            } elseif ($timelineItem instanceof IResponse) {
                foreach ($timelineItem->getAllFeedback() as $feedback) {
                    if ($feedback instanceof Feedback\Model) {
                        $timelineFeedback[] = $feedback;
                    }
                }
            }
        }

        // Get a grid for every feedback in timeline
        $feedbackRubricContexts = new Collection();
        foreach ($timelineFeedback as $feedback) {
            $feedbackRubricContexts[$feedback->getId()] = $this->generateForFeedback(
                $feedback,
                $currentUser,
                $access,
                $publishedOnly
            );
        }
        return $feedbackRubricContexts;
    }
}
