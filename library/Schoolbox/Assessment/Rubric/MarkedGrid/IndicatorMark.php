<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
     * The indicator usage for which this mark is for.
     *
     * @var IndicatorUsage
     */
    private $indicatorUsage;


    /**
     * The descriptor that was selected for this mark.
     *
     * @var Descriptor|null
     */
    private $descriptor;


    /**
     * The actual mark value that was given for the indicator.
     *
     * This can be anywhere within the descriptor's value range.
     *
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
