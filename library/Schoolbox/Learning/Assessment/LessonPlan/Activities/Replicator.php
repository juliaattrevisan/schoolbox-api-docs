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
     * Cache of assessments by course spec.
     *
     * Used to avoid unnecessary double-fetching of the same assessments for the
     * course of a project and its activities.
     *
     * @var array<int, array<int, IAssessable>>
     */
    private array $courseSpecAssessmentCache = [];

    /**
     * Cache of assessments by course implementation.
     *
     * Used to avoid unnecessary double-fetching of the same assessments for the
     * course of a project and its activities.
     *
     * @var array<int, array<int, IAssessable>>
     */
    private array $courseImplAssessmentCache = [];

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
     * @param IAssessable $source
     * @param IAssessable $destination
     * @return void
     * @throws MapperException if somehow the save of a link failed?
     */
    public function replicate(IAssessable $source, IAssessable $destination): void
    {
        // Find ALL assessments within the source and destination course context
        $allSourceAssessments      = $this->extractContextAssessments($source);
        $allDestinationAssessments = $this->extractContextAssessments($destination);

        if ($source instanceof Model && $destination instanceof Model) {
            // Find the linked assessments, and group them by source assessment ID
            $sourceLinkedAssessments = $this->extractLinkedAssessments($source);

            // Find equivalent assessment in destination.
            // If it exists there, create a link in the destination.
            foreach ($sourceLinkedAssessments as $sourceLinkedAssessmentId => $sourceLinkedAssessment) {
                if (isset($allDestinationAssessments[$sourceLinkedAssessmentId])) {
                    $this->linkedAssessmentsMapper->saveLink(
                        $destination,
                        $allDestinationAssessments[$sourceLinkedAssessmentId],
                    );
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
                    $this->linkedAssessmentsMapper->deleteLink(
                        $destination,
                        $allDestinationAssessments[$sourceAssessmentId],
                    );
                }
            }
        }

        // Project importation:
        // figure out which assessment in the destination corresponds to the
        // source, and then replicate any links.
        if ($source instanceof Project && $destination instanceof Project) {
            foreach ($destination->getAssessables() as $destinationAssessment) {
                $sourceAssessment = $this->extractProjectSourceLessonPlan($destinationAssessment, $source);
                if ($sourceAssessment) {
                    $this->replicate($sourceAssessment, $destinationAssessment);
                }
            }
        }
    }

    /**
     * Extract the lesson plan in `$source` which corresponds to
     * `$destinationAssessment`, if there is one.
     *
     * * if `$destinationAssessment` has a spec assessment, this will be the
     *   corresponding spec in `$source`
     * * if `$destinationAssessment` is a spec assessment, this will be the
     *   corresponding implementation in `$source`
     *
     * @param IAssessable $destinationAssessment
     * @param Project     $source
     * @return Model|null
     */
    private function extractProjectSourceLessonPlan(IAssessable $destinationAssessment, Project $source): ?Model
    {
        if (!$destinationAssessment instanceof Model) {
            return null;
        }

        foreach ($source->getAssessables() as $sourceAssessment) {
            if (!$sourceAssessment instanceof Model) {
                continue;
            }
            if ($sourceAssessment->getSpecificationAssessment()?->getId() === $destinationAssessment->getId()) {
                return $sourceAssessment;
            }
            if ($sourceAssessment->getId() === $destinationAssessment->getSpecificationAssessment()?->getId()) {
                return $sourceAssessment;
            }
        }
        return null;
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
     * Extract all assessments within the same course as `$assessment`, and key
     * them by:
     * * their ID, if `$assessment` is a spec
     * * their spec assessment's ID, if `$assessment` is an implementation
     *
     * @param IAssessable $assessment
     * @return array<int, IAssessable>
     */
    private function extractContextAssessments(IAssessable $assessment): array
    {
        $context = $assessment->getBaseContext();

        // Unit spec: key assessments by assessment ID
        // Load the entire course and all of its assessments.
        if ($context instanceof Specification\Unit\Model) {
            $courseSpecId = $context->getCourse()->getId();
            if (isset($this->courseSpecAssessmentCache[$courseSpecId])) {
                return $this->courseSpecAssessmentCache[$courseSpecId];
            }
            foreach ($this->assessmentDataSource->findAllByCourseSpecificationId($courseSpecId) as $courseSpecAssessment) {
                $this->courseSpecAssessmentCache[$courseSpecId] ??= [];
                $this->courseSpecAssessmentCache[$courseSpecId][$courseSpecAssessment->getId()] = $courseSpecAssessment;
                if ($courseSpecAssessment instanceof Project) {
                    foreach ($courseSpecAssessment->getAssessables() as $projectAssessment) {
                        $this->courseSpecAssessmentCache[$courseSpecId][$projectAssessment->getId()] = $projectAssessment;
                    }
                }
            }
            return $this->courseSpecAssessmentCache[$courseSpecId];
        }

        // Unit impl: key assessments by spec assessment ID
        // (don't care about impl assessments not in a spec)
        if ($context instanceof Implementation\Unit\Model) {
            $courseImplId = $context->getCourse()->getId();
            if (isset($this->courseImplAssessmentCache[$courseImplId])) {
                return $this->courseImplAssessmentCache[$courseImplId];
            }
            foreach ($this->assessmentDataSource->findAllByCourseImplementationId($courseImplId) as $courseImplAssessment) {
                $this->courseImplAssessmentCache[$courseImplId] ??= [];
                if ($courseImplAssessment->getSpecificationAssessment()) {
                    $this->courseImplAssessmentCache[$courseImplId][$courseImplAssessment->getSpecificationAssessment()->getId()]
                        = $courseImplAssessment;
                }
                if ($courseImplAssessment instanceof Project) {
                    foreach ($courseImplAssessment->getAssessables() as $projectAssessment) {
                        if ($projectAssessment->getSpecificationAssessment()) {
                            $this->courseImplAssessmentCache[$courseImplId][$projectAssessment->getSpecificationAssessment()->getId()]
                                = $projectAssessment;
                        }
                    }
                }
            }
            return $this->courseImplAssessmentCache[$courseImplId];
        }

        // Neither of the above: return nothing
        return [];
    }
}
