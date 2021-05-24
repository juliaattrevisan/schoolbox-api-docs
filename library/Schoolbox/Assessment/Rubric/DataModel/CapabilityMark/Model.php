<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel\CapabilityMark;

use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Model as CapabilityModel;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * Model for marks on a rubric capability.
 */
class Model
{
    use Id;


    /**
     * @var null|ReturnedWork
     */
    private $submissionReturn;


    /**
     * @var CapabilityModel
     */
    private $capability;


    /**
     * @var bool
     */
    private $valid;


    /**
     * @var float|null
     */
    private $value;


    /**
     * @return null|ReturnedWork
     */
    public function getSubmissionReturn(): ?ReturnedWork
    {
        return $this->submissionReturn;
    }


    /**
     * @param null|ReturnedWork $submissionReturn
     * @return $this
     */
    public function setSubmissionReturn(?ReturnedWork $submissionReturn): self
    {
        $this->submissionReturn = $submissionReturn;
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
