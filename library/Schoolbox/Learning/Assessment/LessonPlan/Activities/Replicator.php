<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\LessonPlan\Activities;

use Schoolbox\Learning\Assessment\DataModel\LessonPlan\LinkedAssessments\Mapper as LinkedAssessmentsMapper;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\LessonPlan\Model;
use Schoolbox\Learning\Course\DataModel\Implementation;
use Schoolbox\Learning\Course\DataModel\Specification;

class Replicator
{
    public function __construct(private LinkedAssessmentsMapper $linkedAssessmentsMapper)
    {
    }


    public function replicate(Model $source, Model $destination): void
    {
        // Find the linked assessments, and group them by source assessment ID
        $sourceLinkedAssessments      = $this->extractLinkedAssessments($source);
        $destinationLinkedAssessments = $this->extractLinkedAssessments($destination);

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
        // If it exists in source but is not linked: remove from destination.
        foreach ($allSourceAssessments as $sourceAssessmentId => $sourceAssessment) {
            if (
                isset($allDestinationAssessments[$sourceAssessmentId])
                && !isset($destinationLinkedAssessments[$sourceAssessmentId])
            ) {
                $this->linkedAssessmentsMapper->deleteLink($destination, $allDestinationAssessments[$sourceAssessmentId]);
            }
        }

//        foreach ($destinationLinkedAssessments as $destinationLinkedAssessmentId => $destinationLinkedAssessment) {
//            if (isset($allSourceAssessments[$destinationLinkedAssessmentId])) {
//                if (!isset($destinationLinkedAssessments[]))
//            }
//        }
    }

    /**
     * @param Model $assessment
     * @return array<int, IAssessable>
     */
    private function extractLinkedAssessments(Model $assessment): array
    {
        $linkedAssessments = [];
        if ($assessment->isSpecification()) {
            foreach ($assessment->getLinkedAssessments() as $linkedAssessment) {
                /** @var IAssessable $linkedAssessment */
                if ($linkedAssessment->isSpecification()) {
                    $linkedAssessments[$linkedAssessment->getId()] = $linkedAssessment;
                }
            }
        } elseif ($assessment->isImplementation()) {
            // Must be an implementation
            foreach ($assessment->getLinkedAssessments() as $linkedAssessment) {
                /** @var IAssessable $linkedAssessment */
                if ($linkedAssessment->isImplementation() && $linkedAssessment->getSpecificationAssessment()) {
                    $linkedAssessments[$linkedAssessment->getSpecificationAssessment()->getId()] = $linkedAssessment;
                }
            }
        }
        return $linkedAssessments;
    }

    /**
     * @param Model $assessment
     * @return array<int, IAssessable>
     */
    private function extractContextAssessments(Model $assessment): array
    {
        $contextAssessments = [];
        $context            = $assessment->getContext();
        if ($context instanceof Specification\Unit\Model) {
            // Unit spec: key assessments by assessment ID
            foreach ($context->getCourse()->getUnits() as $unit) {
                foreach ($unit->getAssessables() as $unitAssessment) {
                    $contextAssessments[$unitAssessment->getId()] = $unitAssessment;
                }
            }
        } elseif ($context instanceof Implementation\Unit\Model) {
            // Unit impl: key assessments by spec assessment ID
            // (don't care about impl assessments not in a spec)
            foreach ($context->getCourse()->getUnits() as $unit) {
                foreach ($unit->getAssessables() as $unitAssessment) {
                    if ($unitAssessment->getSpecificationAssessment()) {
                        $contextAssessments[$unitAssessment->getSpecificationAssessment()->getId()] = $unitAssessment;
                    }
                }
            }
        }
        return $contextAssessments;
    }
}
