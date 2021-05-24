<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
    private const DIRECTION_ASC = 'asc';
    private const DIRECTION_DESC = 'desc';


    /**
     * @var int|null
     */
    private $id;


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
     * Is this descriptor valid?
     * * Either both or neither min and max value may be null: one may not be
     *   null and the other not null
     * * Min value may not be greater than max value
     *
     * @throws \UnexpectedValueException if it's not valid
     */
    public function assertValidity(): void
    {
        $minValueNull = null === $this->minValue;
        $maxValueNull = null === $this->maxValue;
        if ($minValueNull xor $maxValueNull) {
            throw new \UnexpectedValueException('Either both or neither min or max value must be set');
        }

        if ($this->minValue > $this->maxValue) {
            throw new \UnexpectedValueException('Min value must not be higher than max value');
        }
    }


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
     * Retrieve the left-side value of this descriptor.
     *
     * @return float|null
     * @throws \UnexpectedValueException if `assertValidity` fails
     */
    public function retrieveLeftValue(): ?float
    {
        $this->assertValidity();
        if (!$this->hasValue()) {
            return null;
        }
        return $this->direction === self::DIRECTION_ASC ? $this->minValue : $this->maxValue;
    }


    /**
     * Retrieve the right-side value of this descriptor.
     *
     * @return float|null
     * @throws \UnexpectedValueException if `assertValidity` fails
     */
    public function retrieveRightValue(): ?float
    {
        $this->assertValidity();
        if (!$this->hasValue()) {
            return null;
        }
        return $this->direction === self::DIRECTION_ASC ? $this->maxValue : $this->minValue;
    }


    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }


    /**
     * @param int|null $id
     * @return static
     */
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
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


    /**
     * @return bool
     */
    public function isDirectionAscending(): bool
    {
        return $this->direction === self::DIRECTION_ASC;
    }


    /**
     * @return bool
     */
    public function isDirectionDescending(): bool
    {
        return $this->direction === self::DIRECTION_DESC;
    }


    /**
     * @return static
     */
    public function setDirectionAscending(): self
    {
        $this->direction = self::DIRECTION_ASC;
        return $this;
    }


    /**
     * @return static
     */
    public function setDirectionDescending(): self
    {
        $this->direction = self::DIRECTION_DESC;
        return $this;
    }
}
