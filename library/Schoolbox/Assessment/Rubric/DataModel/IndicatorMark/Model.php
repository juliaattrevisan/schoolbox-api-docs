<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel\IndicatorMark;

use Schoolbox\Assessment\Rubric\DataModel\CapabilityMark\Model as CapabilityMarkModel;
use Schoolbox\Assessment\Rubric\DataModel\Descriptor\Model as DescriptorModel;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * Class DescriptorMark
 *
 * Semantically, this class should really be named Selection or similar, but for the sake of matching the db table,
 * mark it is...
 *
 * @package Schoolbox\Assessment\Rubric\DataModel
 */
class Model
{
    use Id;


    /**
     * @var null|CapabilityMarkModel
     */
    private $capabilityMark;


    /**
     * @var null|DescriptorModel
     */
    private $descriptor;


    /**
     * @var bool
     */
    private $valid;


    /**
     * @var float|null
     */
    private $value;


    /**
     * @return null|DescriptorModel
     */
    public function getDescriptor(): ?DescriptorModel
    {
        return $this->descriptor;
    }


    /**
     * @param null|DescriptorModel $descriptor
     * @return static
     */
    public function setDescriptor(?DescriptorModel $descriptor): self
    {
        $this->descriptor = $descriptor;
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


    /**
     * @return null|CapabilityMarkModel
     */
    public function getCapabilityMark(): ?CapabilityMarkModel
    {
        return $this->capabilityMark;
    }


    /**
     * @param null|CapabilityMarkModel $capabilityMark
     * @return static
     */
    public function setCapabilityMark(?CapabilityMarkModel $capabilityMark): self
    {
        $this->capabilityMark = $capabilityMark;
        return $this;
    }
}
