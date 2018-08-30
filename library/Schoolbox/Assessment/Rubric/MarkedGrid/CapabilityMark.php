<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid;

use Schoolbox\Assessment\Rubric\Grid\CapabilityUsage;
use Schoolbox\GenericCollection;

/**
 * Marking of a capability within a marked rubric grid.
 *
 * Represented on the marked grid as a collection of rows.
 */
class CapabilityMark
{
    /**
     * @var CapabilityUsage
     */
    private $capabilityUsage;


    /**
     * @var GenericCollection|DescriptorMark[]
     */
    private $descriptorMarks;


    /**
     * @var float|null
     */
    private $value;


    /**
     * Constructor
     *
     * @param CapabilityUsage $capabilityUsage
     */
    public function __construct(CapabilityUsage $capabilityUsage)
    {
        $this->capabilityUsage = $capabilityUsage;
    }


    /**
     * @return CapabilityUsage
     */
    public function getCapabilityUsage(): CapabilityUsage
    {
        return $this->capabilityUsage;
    }


    /**
     * @return GenericCollection|DescriptorMark[]
     */
    public function getDescriptorMarks(): GenericCollection
    {
        return $this->descriptorMarks;
    }


    /**
     * @param GenericCollection|DescriptorMark[] $descriptorMarks
     * @return static
     */
    public function setDescriptorMarks(GenericCollection $descriptorMarks): self
    {
        $this->descriptorMarks = $descriptorMarks;
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
