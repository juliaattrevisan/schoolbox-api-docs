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

class CriterionUsage
{

    /** @var int */
    public const SEQUENCE_BASE = 1; // Indicator sequence is 1-based, not 0-based

    /**
     * @var Criterion
     */
    private $criterion;

    /**
     * @var SubmissionBox
     */
    private $submission_box;

    /**
     * @var int
     */
    private $sequence;

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
     * @return int|null
     */
    public function getSequence(): ?int
    {
        return $this->sequence;
    }


    /**
     * @param int $sequence
     * @return static
     */
    public function setSequence(int $sequence): self
    {
        $this->sequence = $sequence;
        return $this;
    }

}
