<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

use Schoolbox\Mapper\Traits\Model\Id;

/**
 * A single descriptor within a rubric grid.
 *
 * Represented on the rubric grid as a single cell.
 */
class Descriptor
{
    use Id;


    public const DIRECTION_ASC = 'asc';
    public const DIRECTION_DESC = 'desc';


    /**
     * @var string|null
     */
    private $description;


    /**
     * @var float|null
     */
    private $minValue;


    /**
     * @var float|null
     */
    private $maxValue;


    /**
     * What direction does the capability render in?
     *
     * @var string
     */
    private $direction = self::DIRECTION_ASC;


    /**
     * Does this descriptor have either a min or max value?
     *
     * @return bool
     */
    public function hasValue(): bool
    {
        return !(null === $this->minValue && null === $this->maxValue);
    }


    /**
     * Does this descriptor have a range?
     *
     * @return bool
     */
    public function hasRange(): bool
    {
        return $this->hasValue() && $this->minValue !== $this->maxValue;
    }


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
    public function getMinValue(): ?float
    {
        return $this->minValue;
    }


    /**
     * @param float|null $minValue
     * @return static
     */
    public function setMinValue(?float $minValue): self
    {
        $this->minValue = $minValue;
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
