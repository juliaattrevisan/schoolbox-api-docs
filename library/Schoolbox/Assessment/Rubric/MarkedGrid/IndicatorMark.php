<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid;

use Schoolbox\Assessment\Rubric\Grid\Descriptor;
use Schoolbox\Assessment\Rubric\Grid\IndicatorUsage;

/**
 * Marking of an indicator within a marked rubric grid.
 *
 * Represented on the marked grid as a single row, which may or may not have a
 * single marked descriptor.
 *
 * It may also have a mark value, independent of its descriptor.
 */
class IndicatorMark
{
    /**
     * @var IndicatorUsage
     */
    private $indicatorUsage;


    /**
     * @var Descriptor|null
     */
    private $descriptor;


    /**
     * @var float|null
     */
    private $value;


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
     * @return IndicatorUsage
     */
    public function getIndicatorUsage(): IndicatorUsage
    {
        return $this->indicatorUsage;
    }


    /**
     * @return null|Descriptor
     */
    public function getDescriptor(): ?Descriptor
    {
        return $this->descriptor;
    }


    /**
     * @param null|Descriptor $descriptor
     * @return static
     */
    public function setDescriptor(?Descriptor $descriptor): self
    {
        $this->descriptor = $descriptor;
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
