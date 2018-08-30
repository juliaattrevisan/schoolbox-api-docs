<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid;

use Schoolbox\Assessment\Rubric\Grid\IndicatorUsage;

/**
 * Marking of an indicator within a marked rubric grid.
 *
 * Represented on the marked grid as a single row, which may or may not have a
 * single marked descriptor.
 */
class IndicatorMark
{
    /**
     * @var IndicatorUsage
     */
    private $indicatorUsage;


    /**
     * @var DescriptorMark|null
     */
    private $descriptorMark;


    /**
     * Constructor
     *
     * @param IndicatorUsage $indicatorUsage
     */
    public function __construct(IndicatorUsage $indicatorUsage)
    {
        $this->indicatorUsage = $indicatorUsage;
    }


    /**
     * What's the value for this indicator's mark?
     *
     * @return float|null
     */
    public function calculateValue(): ?float
    {
        if (!$this->descriptorMark) {
            return null;
        }
        return $this->descriptorMark->getValue();
    }


    /**
     * @return IndicatorUsage
     */
    public function getIndicatorUsage(): IndicatorUsage
    {
        return $this->indicatorUsage;
    }


    /**
     * @return null|DescriptorMark
     */
    public function getDescriptorMark(): ?DescriptorMark
    {
        return $this->descriptorMark;
    }


    /**
     * @param null|DescriptorMark $descriptorMark
     * @return static
     */
    public function setDescriptorMark(?DescriptorMark $descriptorMark): self
    {
        $this->descriptorMark = $descriptorMark;
        return $this;
    }
}
