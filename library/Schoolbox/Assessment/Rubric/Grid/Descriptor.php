<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

/**
 * A single descriptor within a rubric grid.
 *
 * Represented on the rubric grid as a single cell.
 */
class Descriptor
{
    /**
     * @var string|null
     */
    private $description;


    /**
     * @var float|null
     */
    private $maxValue;


    /**
     * @return null|string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }


    /**
     * @param null|string $description
     * @return static
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;
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
}
