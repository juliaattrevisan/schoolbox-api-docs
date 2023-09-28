<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\LessonPlan\Activities;

use Schoolbox\Learning\Assessment\DataModel\LessonPlan\LinkedAssessments\Mapper as LinkedAssessmentsMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\LessonPlan\Model;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Course\DataModel\Implementation;
use Schoolbox\Learning\Course\DataModel\Specification;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Replicates lesson plan assessments into another lesson plan.
 */
class Replicator
{
    /**
     * Constructor
     *
     * @param LinkedAssessmentsMapper $linkedAssessmentsMapper Required for saving linked assessments.
     * @param AssessmentDataSource    $assessmentDataSource    Required for fetching all assessments in the course the
     *                                                         replicated lesson plan is in.
     */
    public function __construct(
        private LinkedAssessmentsMapper $linkedAssessmentsMapper,
        private AssessmentDataSource $assessmentDataSource,
    ) {
    }

    /**
     * Replicate linked assessments in `$source` into `$destination`.
     *
     * If exporting a course or single assessment into a class: `$source` will
     * be a spec assessment, and `$destination` will be an implementation of
     * `$source`.
     *
     * If importing a class assessment into a course: `$source` will be an
     * implementation, and `$destination` will be `$source`'s spec assessment.
     *
     * Either way:
     * * if a linked assessment from `$source` exists in `$destination`'s
     *   course context: link the destination copy
     * * if an assessment exists in `$source`'s context but is no longer linked
     *   in `$source`: unlink the destination copy
     *
     * @param Model $source
     * @param Model $destination
     * @return void
     * @throws MapperException if somehow the save of a link failed?
     */
    public function replicate(Model $source, Model $destination): void
    {
        // Find the linked assessments, and group them by source assessment ID
        $sourceLinkedAssessments = $this->extractLinkedAssessments($source);

        // Find ALL assessments within the source and destination course context
        $allSourceAssessments      = $this->extractContextAssessments($source);
        $allDestinationAssessments = $this->extractContextAssessments($destination);

        // Find equivalent assessment in destination.
        // If it exists there, create a link in the destination.
        foreach ($sourceLinkedAssessments as $sourceLinkedAssessmentId => $sourceLinkedAssessment) {
            if (isset($allDestinationAssessments[$sourceLinkedAssessmentId])) {
                $this->linkedAssessmentsMapper->saveLink($destination, $allDestinationAssessments[$sourceLinkedAssessmentId]);
            }
        }

        // Find equivalent assessment in source.
        // If it exists in destination but is not linked in the source:
        // remove from destination.
        foreach ($allSourceAssessments as $sourceAssessmentId => $sourceAssessment) {
            if (
                isset($allDestinationAssessments[$sourceAssessmentId])
                && !isset($sourceLinkedAssessments[$sourceAssessmentId])
            ) {
                $this->linkedAssessmentsMapper->deleteLink($destination, $allDestinationAssessments[$sourceAssessmentId]);
            }
        }
    }

    /**
     * Extract linked assessments from `$lessonPlan`, and key them by:
     * * their ID, if `$lessonPlan` is a spec
     * * their spec assessment's ID, if `$lessonPlan` is an implementation
     *
     * @param Model $lessonPlan
     * @return array<int, IAssessable>
     */
    private function extractLinkedAssessments(Model $lessonPlan): array
    {
        $linkedAssessments = [];
        if ($lessonPlan->isSpecification()) {
            foreach ($lessonPlan->getLinkedAssessments() as $linkedAssessment) {
                /** @var IAssessable $linkedAssessment */
                $linkedAssessments[$linkedAssessment->getId()] = $linkedAssessment;
            }
        } elseif ($lessonPlan->isImplementation()) {
            foreach ($lessonPlan->getLinkedAssessments() as $linkedAssessment) {
                /** @var IAssessable $linkedAssessment */
                if ($linkedAssessment->getSpecificationAssessment()) {
                    $linkedAssessments[$linkedAssessment->getSpecificationAssessment()->getId()] = $linkedAssessment;
                }
            }
        }
        return $linkedAssessments;
    }

    /**
     * Extract all assessment within the same course as `$lessonPlan`, and key
     * them by:
     * * their ID, if `$lessonPlan` is a spec
     * * their spec assessment's ID, if `$lessonPlan` is an implementation
     *
     * @param Model $lessonPlan
     * @return array<int, IAssessable>
     */
    private function extractContextAssessments(Model $lessonPlan): array
    {
        $contextAssessments = [];
        $context            = $lessonPlan->getContext();
        if ($context instanceof Specification\Unit\Model) {
            // Unit spec: key assessments by assessment ID
            // Load the entire course and all of its assessments
            $unitAssessments = $this->assessmentDataSource->findAllByCourseSpecificationId($context->getCourse()->getId());
            foreach ($unitAssessments as $unitAssessment) {
                $contextAssessments[$unitAssessment->getId()] = $unitAssessment;
                if ($unitAssessment instanceof Project) {
                    foreach ($unitAssessment->getAssessables() as $projectAssessment) {
                        $contextAssessments[$projectAssessment->getId()] = $projectAssessment;
                    }
                }
            }
        } elseif ($context instanceof Implementation\Unit\Model) {
            // Unit impl: key assessments by spec assessment ID
            // (don't care about impl assessments not in a spec)
            $unitAssessments = $this->assessmentDataSource->findAllByCourseImplementationId($context->getCourse()->getId());
            foreach ($unitAssessments as $unitAssessment) {
                if ($unitAssessment->getSpecificationAssessment()) {
                    $contextAssessments[$unitAssessment->getSpecificationAssessment()->getId()] = $unitAssessment;
                }
                if ($unitAssessment instanceof Project) {
                    foreach ($unitAssessment->getAssessables() as $projectAssessment) {
                        if ($projectAssessment->getSpecificationAssessment()) {
                            $contextAssessments[$projectAssessment->getSpecificationAssessment()->getId()]
                                = $projectAssessment;
                        }
                    }
                }
            }
        }
        return $contextAssessments;
    }
}
