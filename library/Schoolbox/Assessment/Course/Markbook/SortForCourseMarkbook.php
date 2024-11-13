<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Markbook;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IAssessableComparator;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImplementation;

/**
 * Suitable for sorting assessments on the course-markbook.
 */
class SortForCourseMarkbook implements IAssessableComparator
{
    public function compare(IAssessable $left, IAssessable $right): int
    {
        $callable = $this->getCallable();

        return $callable($left, $right);
    }


    /**
     * Note that we have a combination of assessment-specs and impls to sort.
     *
     * Assumes that assessments reside in unit-impls or unit-specs.
     *
     * Sorts by:
     * 1. Unit-spec name
     * 2. AssessmentSpecs before AssessmentImpls
     * if(both impl)
     *     2a. course-import folder name
     *     2b. course-import first unit-impl open date
     *     2c. course-import id
     * 3. Base-assessment sequence
     * 4  Projects, then project sub-assessments by sequence
     *
     * @inheritdoc
     */
    public function getCallable(): callable
    {
        return function (IAssessable $left, IAssessable $right) {
            // 1. Unit-spec sequence
            $unitSpecNameCompare = $this->compareUnitSpecSequence($left, $right);

            if (0 !== $unitSpecNameCompare) {
                return $unitSpecNameCompare;
            }

            // 2. AssessmentSpecs before AssessmentImpls
            $specImplCompare = $this->compareSpecImpl($left, $right);
            if (0 !== $specImplCompare) {
                return $specImplCompare;
            }

            if ($left->isImplementation() && $right->isImplementation()) {
                // 2a. course-import folder name
                $specImplCompare = $this->compareCourseImportFolderName($left, $right);
                if (0 !== $specImplCompare) {
                    return $specImplCompare;
                }

                // 2b. course-import first unit-impl open date
                $specImplCompare = $this->compareCourseImportFirstUnit($left, $right);
                if (0 !== $specImplCompare) {
                    return $specImplCompare;
                }

                // 2c. course-import id
                $courseImportId = $this->compareCourseImportId($left, $right);
                if (0 !== $courseImportId) {
                    return $courseImportId;
                }
            }

            // 3. Base-assessment sequence
            $baseSequenceCompare = $this->compareBaseSequence($left, $right);
            if (0 !== $baseSequenceCompare) {
                return $baseSequenceCompare;
            }

            // 4  Projects, then project sub-assessments by sequence
            return $this->compareIsProjectOrSubAssessable($left, $right);
        };
    }


    /**
     * Sort by unit-spec folder sequence.
     *
     * Doesn't matter whether assessment is impl or spec. Always punches down to unit-spec.
     *
     * @param IAssessable $left
     * @param IAssessable $right
     * @return int
     */
    private function compareUnitSpecSequence(IAssessable $left, IAssessable $right): int
    {
        if ($left->isSpecification()) {
            $leftSequence = $left->getBaseContext()->getFolder()->getSequence();
            $leftSpecName = $left->getBaseContext()->getTitle();
        } else {
            /** @var UnitImplementation\Model $unitImpl */
            $unitImpl     = $left->getBaseContext();
            $leftSequence = $unitImpl->getSpecification()->getFolder()->getSequence();
            $leftSpecName = $unitImpl->getSpecification()->getTitle();
        }
        if ($right->isSpecification()) {
            $rightSequence = $right->getBaseContext()->getFolder()->getSequence();
            $rightSpecName = $right->getBaseContext()->getTitle();
        } else {
            /** @var UnitImplementation\Model $unitImpl */
            $unitImpl      = $right->getBaseContext();
            $rightSequence = $unitImpl->getSpecification()->getFolder()->getSequence();
            $rightSpecName = $unitImpl->getSpecification()->getTitle();
        }

        return  (
            ($leftSequence === $rightSequence)
            ? $rightSpecName <=> $leftSpecName
            : $rightSequence <=> $leftSequence
        );
    }


    /**
     * Sorts Assessment Specs before Assessment Impls
     *
     * @param IAssessable $left
     * @param IAssessable $right
     * @return int
     */
    private function compareSpecImpl(IAssessable $left, IAssessable $right): int
    {
        if ($left->isSpecification()) {
            // Both specs, or left:spec + right:impl
            return $right->isSpecification() ? 0 : -1;
        }

        // left:impl + right:spec, or both impls
        return $right->isSpecification() ? +1 : 0;
    }


    /**
     * Sort two assessment implementations by their course-import's folder name
     *
     * Will return 0 if course import twice to same folder.
     *
     * @param IAssessable $left
     * @param IAssessable $right
     * @return int
     */
    private function compareCourseImportFolderName(IAssessable $left, IAssessable $right): int
    {
        /** @var UnitImplementation\Model $leftUnitImpl */
        $leftUnitImpl = $left->getBaseContext();

        /** @var UnitImplementation\Model $rightUnitImpl */
        $rightUnitImpl = $right->getBaseContext();

        return strnatcasecmp(
            $leftUnitImpl->getCourse()->getFolder()->getTitle(),
            $rightUnitImpl->getCourse()->getFolder()->getTitle()
        );
    }


    /**
     * Sort two assessment implementations by their course-import's first unit's open date
     *
     * May return 0 if both unit-imports open at same time
     *
     * @param IAssessable $left
     * @param IAssessable $right
     * @return int
     */
    private function compareCourseImportFirstUnit(IAssessable $left, IAssessable $right): int
    {
        /** @var UnitImplementation\Model $leftUnitImpl */
        $leftUnitImpl = $left->getBaseContext();

        /** @var UnitImplementation\Model $rightUnitImpl */
        $rightUnitImpl = $right->getBaseContext();

        $leftCourse  = $leftUnitImpl->getCourse();
        $rightCourse = $rightUnitImpl->getCourse();

        /** @var UnitImplementation\Model $firstLeftUnitImpl */
        $firstLeftUnitImpl = null;
        foreach ($leftCourse->getUnits() as $unitImpl) {
            if (!$firstLeftUnitImpl || $unitImpl->getOpenDate()->lt($firstLeftUnitImpl->getOpenDate())) {
                $firstLeftUnitImpl = $unitImpl;
            }
        }

        /** @var UnitImplementation\Model $firstRightUnitImpl */
        $firstRightUnitImpl = null;
        foreach ($rightCourse->getUnits() as $unitImpl) {
            if (!$firstRightUnitImpl || $unitImpl->getOpenDate()->lt($firstRightUnitImpl->getOpenDate())) {
                $firstRightUnitImpl = $unitImpl;
            }
        }

        return $firstLeftUnitImpl->getOpenDate()->compare($firstRightUnitImpl->getOpenDate());
    }


    /**
     * Sort two assessment implementations by their course-import's ids
     *
     * Returns 0 if both in same course-import
     *
     * @param IAssessable $left
     * @param IAssessable $right
     * @return int
     */
    private function compareCourseImportId(IAssessable $left, IAssessable $right): int
    {
        /** @var UnitImplementation\Model $leftUnitImpl */
        $leftUnitImpl = $left->getBaseContext();

        /** @var UnitImplementation\Model $rightUnitImpl */
        $rightUnitImpl = $right->getBaseContext();

        return $leftUnitImpl->getCourse() <=> $rightUnitImpl->getCourse()->getId();
    }


    /**
     * Sorts assessments by base sequence
     *
     * @param IAssessable $left
     * @param IAssessable $right
     * @return int
     */
    private function compareBaseSequence(IAssessable $left, IAssessable $right): int
    {
        return $left->getBaseAssessable()->getSequence() <=> $right->getBaseAssessable()->getSequence();
    }


    /**
     * Sorts a project and their sub-assessables by project first, then sub-assessments by sequence
     *
     * @param IAssessable $left
     * @param IAssessable $right
     * @return int
     */
    private function compareIsProjectOrSubAssessable(IAssessable $left, IAssessable $right): int
    {
        if ($left instanceof Project) {
            return -1;
        }
        if ($right instanceof Project) {
            return +1;
        }

        // Both are project-subAssessables. Sort by sequence
        return $left->getSequence() <=> $right->getSequence();
    }
}
