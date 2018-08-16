<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid;

use Schoolbox\Assessment\Rubric\Grid\Descriptor;

/**
 * Marking of a single descriptor within a marked rubric grid.
 *
 * Represented on the marked grid as a single marked descriptor cell.
 */
class DescriptorMark
{
    /**
     * @var Descriptor
     */
    private $descriptor;


    /**
     * @var float|null
     */
    private $value;


    /**
     * Constructor
     *
     * @param Descriptor $descriptor
     */
    public function __construct(Descriptor $descriptor)
    {
        $this->descriptor = $descriptor;
    }


    /**
     * @return Descriptor
     */
    public function getDescriptor(): Descriptor
    {
        return $this->descriptor;
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
