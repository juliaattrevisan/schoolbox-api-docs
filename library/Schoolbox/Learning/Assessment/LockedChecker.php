<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Schoolbox\Assessment\Term;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * 'Locked' assessments cannot be modified, or receive responses or feedback.
 *
 * Superusers can still perform these actions - but that's not this class's
 * responsibility. That should be performed by module auth rules and controller code etc.
 */
class LockedChecker
{
    /**
     * @param Term\Mapper $termMapper
     */
    public function __construct(private Term\Mapper $termMapper)
    {
    }


    /**
     * Is the assessment locked from modification?
     *
     * @param IAssessable $assessment
     * @return bool
     * @throws \InvalidArgumentException If assessment is not an assessment spec and due date is null.
     */
    public function isLockedForModify(IAssessable $assessment): bool
    {
        if ($assessment->getContext()->isSpecificationContext()) {
            // Assessment Specifications can always be modified
            return false;
        }
        return $this->isDueInArchivedTerm($assessment);
    }


    /**
     * Is the assessment locked from receiving responses or feedback?
     *
     * @param IAssessable $assessment
     * @return bool
     * @throws \InvalidArgumentException If assessment is not an assessment spec and due date is null.
     */
    public function isLockedForInteractions(IAssessable $assessment): bool
    {
        if ($assessment->getContext()->isSpecificationContext()) {
            // Assessment Specifications can never have responses or feedback
            return true;
        }
        return $this->isDueInArchivedTerm($assessment);
    }


    /**
     * Is the assessment due in an archived term, or no term at all?
     *
     * @param IAssessable $assessment
     * @return bool
     * @throws \InvalidArgumentException If due date is null.
     */
    private function isDueInArchivedTerm(IAssessable $assessment): bool
    {
        $dueDate = $assessment->getDueAt();

        if (!$dueDate) {
            throw new \InvalidArgumentException('Cannot find containing term using a null date');
        }

        try {
            $dueInTerm = $this->termMapper->findForDateAndYearLevel(
                $dueDate,
                $assessment->getBaseContext()->getFolder()->getYearLevels()?->first());
        } catch (MapperException $e) {
            return true;
        }

        // No term? Or term is archived?
        return !$dueInTerm->getId() || $dueInTerm->getArchived();
    }

    /**
     * Can we delete this assessment?
     *
     * @param IAssessable $assessment
     * @return bool
     */
    public function isLockedForDeletion(IAssessable $assessment): bool
    {
        if ($assessment->getContext()->isSpecificationContext()) {
            // Assessment Specifications can be deleted at any time
            return false;
        }
        return $this->isDueInArchivedTerm($assessment);
    }
}
