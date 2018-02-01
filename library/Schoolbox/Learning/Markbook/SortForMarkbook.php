<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Markbook;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IAssessableComparator;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImplementation;

/**
 * Suitable for sorting assessments on the markbook.
 */
class SortForMarkbook implements IAssessableComparator
{

    /**
     * @inheritdoc
     */
    public function compare(IAssessable $left, IAssessable $right): int
    {
        $callable = $this->getCallable();
        return $callable($left, $right);
    }


    /**
     * Sorts by:
     * 1. Ultimate folder context id
     * 2. UnitImpl open date, then directly in folder
     * 3. Base-assessment sequence in in unit-impl, or base-assessment due-date in directly on folder
     * 4. Project, then project sub-assessments by sequence
     *
     * @inheritdoc
     */
    public function getCallable(): callable
    {
        return function (IAssessable $left, IAssessable $right) {
            // Sort first by folder
            $folderCompare = $this->compareFolders($left, $right);
            if (0 !== $folderCompare) {
                return $folderCompare;
            }

            // Both in same folder, sort by unit-impl open date, before directly in folder
            $folderUnitImplCompare = $this->compareUnitImplOrFolder($left, $right);
            if (0 !== $folderUnitImplCompare) {
                return $folderUnitImplCompare;
            }

            // Either both in same unit-impl, or both directly in same folder.
            // (Base-assessment is a assessment not in a project, or the parent of project sub-assessables)
            // Sort by base-sequence if in unit-impl, or base-due-date if directly on folder
            $baseCompare = $this->compareBaseSequenceOrDueDate($left, $right);
            if (0 !== $baseCompare) {
                return $baseCompare;
            }

            // Same base-assessment sequence, in same unit-impl or direct on folder.
            // Place sub-assessments to right of their parent project. Sort sub-assessments by sequence
            return $this->compareIsProjectOrSubAssessable($left, $right);
        };
    }


    /**
     * Sort by base context folder id
     *
     * @param IAssessable $left
     * @param IAssessable $right
     * @return int
     */
    private function compareFolders(IAssessable $left, IAssessable $right): int
    {
        return $left->getBaseContext()->getFolder()->getId() <=> $right->getBaseContext()->getFolder()->getId();
    }


    /**
     * Sort by in unit-impl open date, then directly in folder
     *
     * @param IAssessable $left
     * @param IAssessable $right
     * @return int
     */
    private function compareUnitImplOrFolder(IAssessable $left, IAssessable $right): int
    {
        // Is one assessment in a unit-impl, the other in a folder?
        // Ignore project intermediate context
        $leftBaseContext  = $left->getBaseContext();
        $rightBaseContext = $right->getBaseContext();

        // Left inside unit impl?
        if ($leftBaseContext instanceof UnitImplementation\Model) {
            // Right inside unit impl?
            if ($rightBaseContext instanceof UnitImplementation\Model) {
                // sort by unit impl open date
                return $leftBaseContext->getOpenDate()->compare($rightBaseContext->getOpenDate());
            }
            // Right is directly in folder.
            return -1;
        }
        // Left is directly on folder. Is right in unit impl?
        if ($rightBaseContext instanceof UnitImplementation\Model) {
            return +1;
        }
        // Both directly in same folder
        return 0;
    }


    /**
     * Sorts assessments by sequence if both in unit-impl, or due-date directly.
     * Assessables must be in same unit-impl, or both directly on same folder.
     *
     * @param IAssessable $left
     * @param IAssessable $right
     * @return int
     */
    private function compareBaseSequenceOrDueDate(IAssessable $left, IAssessable $right): int
    {
        $baseLeft  = $left->getBaseAssessable();
        $baseRight = $right->getBaseAssessable();

        // Are they both in the same unit impl
        if ($baseLeft->getBaseContext() instanceof UnitImplementation\Model) {
            // Compare by base-assessment sequence
            return $baseLeft->getSequence() <=> $baseRight->getSequence();
        }
        // Both directly on same folder, compare by due-date
        return $baseLeft->getDueAt()->compare($baseRight->getDueAt());
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
