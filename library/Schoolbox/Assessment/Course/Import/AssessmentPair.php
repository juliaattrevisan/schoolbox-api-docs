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
     * @param IAssessable|null $specification
     * @param IAssessable|null $implementation
     */
    public function __construct(IAssessable $specification = null, IAssessable $implementation = null)
    {
        // Ensure at least one is present
        if(is_null($specification) && is_null($implementation)){
            throw new \DomainException('Cannot have null Specification and null Implementation');
        }

        // Are we setting an Implementation ?
        if($implementation instanceof IAssessable) {
            // Ensure Implementation is not deleted
            if (is_null($implementation->getDeletedAt())) {
                throw new \DomainException('Implementation date must be set to perform validity check: ' . $implementation->getId());
            } elseif (!$implementation->getDeletedAt() instanceof \Alaress_Date_NullDate) {
                throw new \DomainException('Implementation cannot be deleted: ' . $implementation->getId());
            }
        }
        // else we must have just received a Spec
        else {
            // Ensure it is not deleted
            if (is_null($specification->getDeletedAt())) {
                throw new \DomainException('Specification date must be set to perform validity check: ' . $specification->getId());
            } elseif (!$specification->getDeletedAt() instanceof \Alaress_Date_NullDate) {
                throw new \DomainException('Specification cannot be deleted if no Implementation present: ' . $specification->getId());
            }
        }

        $this->specification = $specification;
        $this->implementation = $implementation;
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