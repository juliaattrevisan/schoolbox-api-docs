<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course\Import;

use Schoolbox\GenericCollection;
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
     * Recursive to permit any level of Project nesting
     *
     * @var static[]|GenericCollection
     */
    private $assessmentPairs;

    /**
     * If this pair is a sub-pair (ie inside a Project) this points to parent Project pair.
     *
     * @var AssessmentPair|null
     */
    private $parentPair;

    /**
     * @param IAssessable|null $spec
     * @param IAssessable|null $impl
     * @param GenericCollection|AssessmentPair[]|null $subPairs
     */
    public function __construct(IAssessable $spec = null, IAssessable $impl = null, GenericCollection $subPairs = null)
    {
        // Ensure at least one is present
        if (is_null($spec) && is_null($impl)) {
            throw new \DomainException('Cannot have null Specification and null Implementation');
        }

        // Are we setting an Implementation ?
        if ($impl instanceof IAssessable) {
            // Ensure Implementation is not deleted
            if ($impl->isDeleted()) {
                throw new \DomainException('AssessmentImpl cannot be deleted: ' . $impl->getId());
            }
        } else {
            // else we must have just received a Spec
            // Ensure it is not deleted
            if ($spec->isDeleted()) {
                throw new \DomainException("AssessmentSpec cannot be deleted if no AssessmentImpl present: {$spec->getId()}");
            }
        }

        $this->specification = $spec;
        $this->implementation = $impl;
        $this->assessmentPairs = $subPairs ?: new GenericCollection(AssessmentPair::class);
        foreach ($this->assessmentPairs as $subPair) {
            $subPair->parentPair = $this;
        }
    }

    /**
     * If this pair is a sub-pair of a Project-pair, return the Project-pair.
     *
     * @return AssessmentPair|null
     */
    public function getParentPair()
    {
        return $this->parentPair;
    }

    /**
     * Is this Assessment un-imported?
     *
     * @return bool
     */
    public function isSpecOnly()
    {
        return $this->specification && !$this->implementation;
    }

    /**
     * Was this Assessment created directly in a Unit Implementation ?
     *
     * @return bool
     */
    public function isImplOnly()
    {
        return !$this->specification && $this->implementation;
    }

    /**
     * Has this Assessment been imported from Unit Spec into Unit Impl ?
     *
     * @return bool
     */
    public function isPair()
    {
        return $this->specification && $this->implementation;
    }

    /**
     * Gets the count of assessments in this assessment and its sub-assessments.
     *
     * For non-projects, this returns 1. For Projects it returns 1(this) + recursive sub-assessment count
     *
     * @return int
     */
    public function getAssessmentCount()
    {
        $count = 1;
        foreach ($this->assessmentPairs as $subPair) {
            $count += $subPair->getAssessmentCount();
        }
        return $count;
    }

    /**
     * Gets the count of assessments implementation in this assessment and its sub-assessments.
     *
     * A pair counts towards count only if it contains an AssessmentImpl
     *
     * @return int
     */
    public function getAssessmentImplCount()
    {
        $count = $this->implementation ? 1 : 0;
        foreach ($this->assessmentPairs as $subPair) {
            $count += $subPair->getAssessmentImplCount();
        }
        return $count;
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

    /**
     * @return GenericCollection|static[]
     */
    public function getSubPairs()
    {
        return $this->assessmentPairs;
    }
}
