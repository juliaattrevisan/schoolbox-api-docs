<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Schoolbox\Assessment\Term;

/**
 * 'Locked' assessments cannot be modified, or receive responses or feedback.
 *
 * Superusers can still perform these actions - but that's not this class's
 * responsibility. That should be performed by module auth rules and controller code etc.
 */
class LockedChecker
{

    /** @var Term\Mapper */
    private $termMapper;


    /**
     * @param Term\Mapper $termMapper
     */
    public function __construct(Term\Mapper $termMapper)
    {
        $this->termMapper = $termMapper;
    }


    /**
     * Is the assessment locked from modification?
     *
     * @param IAssessable $assessment
     * @return bool
     * @throws \InvalidArgumentException If assessment is not an assessment spec and due date is null.
     */
    public function isLockedForModify(IAssessable $assessment)
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
    public function isLockedForInteractions(IAssessable $assessment)
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
    private function isDueInArchivedTerm(IAssessable $assessment)
    {
        $dueDate = $assessment->getDueAt();

        if (!$dueDate || $dueDate instanceof \Alaress_Date_NullDate) {
            throw new \InvalidArgumentException('Cannot find containing term using a null date');
        }

        $dueInTerm = $this->termMapper->findForDate($dueDate);

        // No term? Or term is archived?
        return !$dueInTerm || !$dueInTerm->getId() || $dueInTerm->getArchived();
    }

    /**
     * Is the assessment due in an archived term, or no term at all?
     *
     * @param IAssessable $assessment
     * @return bool
     * @throws \InvalidArgumentException If due date is null.
     */
    public function isLockedForDeletion(IAssessable $assessment)
    {
        return $this->isDueInArchivedTerm($assessment);
    }
}
