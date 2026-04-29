<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel\CapabilityMark;

use Schoolbox\Assessment\Rubric\DataModel\Capability\Model as CapabilityModel;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * Model for marks on a rubric capability.
 */
class Model
{
    use Id;


    /**
     * The primary identifier of a row that represents a feedback from one of the learning activity feedback tables
     * such as submission_return, reporting_feedback, etc. With the feedback_table value, this forms a tuple that tells
     * us where a feedback is located for a particular capability.
     *
     * @var int|null
     */
    private ?int $feedbackId = null;


    /**
     * The database schema table name for where a feedback_id can be found. Could be any of the learning activity
     * feedback tables such as submission_return, reporting_feedback, etc.
     *
     * @var string|null
     */
    private ?string $feedbackTable = null;


    /**
     * @var CapabilityModel
     */
    private CapabilityModel $capability;


    /**
     * @var bool
     */
    private bool $valid;


    /**
     * The human-readable value for this capability mark.
     */
    private ?string $humanReadableValue = null;


    /**
     * @var float|null
     */
    private ?float $value = null;



    /* Getters/Setters
     */

    /**
     * @return int|null
     */
    public function getFeedbackId(): ?int
    {
        return $this->feedbackId;
    }


    /**
     * @param int|null $feedbackId
     * @return Model
     */
    public function setFeedbackId(?int $feedbackId): self
    {
        $this->feedbackId = $feedbackId;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getFeedbackTable(): ?string
    {
        return $this->feedbackTable;
    }


    /**
     * @param string|null $feedbackTable
     * @return $this
     */
    public function setFeedbackTable(?string $feedbackTable): self
    {
        $this->feedbackTable = $feedbackTable;
        return $this;
    }


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
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->valid;
    }


    /**
     * @deprecated Mapper method only
     * @return bool
     */
    public function getValid(): bool
    {
        return $this->isValid();
    }


    /**
     * @param boolean $valid
     * @return static
     */
    public function setValid($valid): self
    {
        $this->valid = $valid;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getHumanReadableValue(): ?string
    {
        return $this->humanReadableValue;
    }


    /**
     * @param string|null $humanReadableValue
     * @return static
     */
    public function setHumanReadableValue(?string $humanReadableValue): self
    {
        $this->humanReadableValue = $humanReadableValue;
        return $this;
    }


    /**
     * @return float|null
     */
    public function getValue(): ?float
    {
        return $this->value;
    }


    /**
     * @param float|null $value
     * @return static
     */
    public function setValue(?float $value): self
    {
        $this->value = $value;
        return $this;
    }
}
