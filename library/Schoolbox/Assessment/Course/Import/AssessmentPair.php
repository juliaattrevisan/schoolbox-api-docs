<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Assessment\Course\Import;

use Schoolbox\Learning\Assessment\IAssessable;

/**
 * Encapsulates a logically-related pair of Assessments, where one is a Specification, and the other
 * is an Implementation that was cloned from the Spec
 *
 * Intentionally no setters() to enforce validity checks during construction
 */
class AssessmentPair
{
    /**
     * @var IAssessable|null
     */
    private $specification;

    /**
     * @var IAssessable|null
     */
    private $implementation;

    /**
     * @param IAssessable|null $spec
     * @param IAssessable|null $impl
     */
    public function __construct(IAssessable $spec = null, IAssessable $impl = null)
    {
        // Ensure at least one is present
        if (is_null($spec) && is_null($impl)) {
            throw new \DomainException('Cannot have null Specification and null Implementation');
        }

        // Are we setting an Implementation ?
        if ($impl instanceof IAssessable) {
            // Ensure Implementation is not deleted
            if (is_null($impl->getDeletedAt())) {
                throw new \DomainException("AssessmentImpl deletedAt required for integrity check: {$impl->getId()}");
            } elseif (!$impl->getDeletedAt() instanceof \Alaress_Date_NullDate) {
                throw new \DomainException('AssessmentImpl cannot be deleted: ' . $impl->getId());
            }
        } // else we must have just received a Spec
        else {
            // Ensure it is not deleted
            if (is_null($spec->getDeletedAt())) {
                throw new \DomainException("AssessmentSpec deletedAt required for integrity check: {$spec->getId()}");
            } elseif (!$spec->getDeletedAt() instanceof \Alaress_Date_NullDate) {
                throw new \DomainException("AssessmentSpec cannot be deleted if no AssessmentImpl present: {$spec->getId()}");
            }
        }

        $this->specification = $spec;
        $this->implementation = $impl;
    }

    /**
     * @return null|IAssessable
     */
    public function getSpecification()
    {
        return $this->specification;
    }

    /**
     * @return null|IAssessable
     */
    public function getImplementation()
    {
        return $this->implementation;
    }
}