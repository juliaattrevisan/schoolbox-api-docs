<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\DataModel\CapabilityUsage;

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Model as CapabilityModel;

/**
 * Model for usage of a rubric capability on a due work.
 */
class Model
{

    /** @var int */
    public const SEQUENCE_BASE = 1; // Capability sequence is 1-based, not 0-based

    /**
     * @var CapabilityModel
     */
    private $capability;


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
     * @return CapabilityModel
     */
    public function getCapability(): CapabilityModel
    {
        return $this->capability;
    }


    /**
     * @param CapabilityModel $capability
     * @return static
     */
    public function setCapability(CapabilityModel $capability): self
    {
        $this->capability = $capability;
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
