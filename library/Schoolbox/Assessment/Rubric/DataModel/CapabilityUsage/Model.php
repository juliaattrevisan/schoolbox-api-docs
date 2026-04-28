<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\DataModel\CapabilityUsage;

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
     * @var null|\Schoolbox\Assessment\Rubric\Instance\Model
     */
    private ?\Schoolbox\Assessment\Rubric\Instance\Model $rubricInstance;


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
     * @return null|\Schoolbox\Assessment\Rubric\Instance\Model
     */
    public function getRubricInstance(): ?\Schoolbox\Assessment\Rubric\Instance\Model
    {
        return $this->rubricInstance;
    }


    /**
     * @param null|\Schoolbox\Assessment\Rubric\Instance\Model $rubricInstance
     * @return $this
     */
    public function setRubricInstance(?\Schoolbox\Assessment\Rubric\Instance\Model $rubricInstance): self
    {
        $this->rubricInstance = $rubricInstance;
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
