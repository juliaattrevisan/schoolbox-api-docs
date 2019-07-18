<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\DataModel;

use Schoolbox\Assessment\Component\SubmissionBox;

/**
 * Model for usage of a rubric group on a due work.
 */
class GroupUsage
{

    /** @var int */
    public const SEQUENCE_BASE = 1; // Capability sequence is 1-based, not 0-based

    /**
     * @var Group
     */
    private $group;


    /**
     * @var SubmissionBox
     */
    private $submissionBox;


    /**
     * @var float|null
     */
    private $maxValue;


    /**
     * @var int
     */
    private $sequence;


    /**
     * @return Group
     */
    public function getGroup(): Group
    {
        return $this->group;
    }


    /**
     * @param Group $group
     * @return static
     */
    public function setGroup(Group $group): self
    {
        $this->group = $group;
        return $this;
    }


    /**
     * @return SubmissionBox
     */
    public function getSubmissionBox(): SubmissionBox
    {
        return $this->submissionBox;
    }


    /**
     * @param SubmissionBox $submissionBox
     * @return static
     */
    public function setSubmissionBox(SubmissionBox $submissionBox): self
    {
        $this->submissionBox = $submissionBox;
        return $this;
    }


    /**
     * @return float|null
     */
    public function getMaxValue(): ?float
    {
        return $this->maxValue;
    }


    /**
     * @param float|null $maxValue
     * @return static
     */
    public function setMaxValue(?float $maxValue): self
    {
        $this->maxValue = $maxValue;
        return $this;
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
