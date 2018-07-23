<?php
/**
 * Model for rubric criterion usage.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\GenericCollection;

class CriterionUsage
{

    /**
     * @var Criterion
     */
    private $criterion;

    /**
     * @var SubmissionBox
     */
    private $submission_box;

    /**
     * 0-based
     *
     * @var int
     */
    private $sequence = 0;

    /**
     * @var Descriptor[]|GenericCollection
     */
    private $descriptors;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->descriptors = new GenericCollection(Descriptor::class);
    }

    /**
     * @param  Criterion $criterion
     * @return $this
     */
    public function setCriterion(Criterion $criterion)
    {
        $this->criterion = $criterion;
        return $this;
    }

    /**
     * @return Criterion
     */
    public function getCriterion()
    {
        return $this->criterion;
    }

    /**
     * @param  SubmissionBox $submission_box
     * @return $this
     */
    public function setSubmissionBox(SubmissionBox $submission_box)
    {
        $this->submission_box = $submission_box;
        return $this;
    }

    /**
     * @return SubmissionBox
     */
    public function getSubmissionBox()
    {
        return $this->submission_box;
    }


    /**
     * @return int
     */
    public function getSequence()
    {
        return $this->sequence;
    }


    /**
     * @param int $sequence
     * @return static
     */
    public function setSequence($sequence)
    {
        $this->sequence = $sequence;
        return $this;
    }

    /**
     * @return Descriptor[]|GenericCollection
     */
    public function getDescriptors(): GenericCollection
    {
        return $this->descriptors;
    }

    /**
     * @param Descriptor[]|GenericCollection $descriptors
     * @return static
     */
    public function setDescriptors(GenericCollection $descriptors): self
    {
        $this->descriptors = $descriptors;
        return $this;
    }
}
