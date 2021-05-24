<?php

/**
 * Calculates the min and max values for a single descriptor
 *
 * @author      Dorothea Baker <dorothea.baker@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork;

class DescriptorMetadata
{
    /**
     * @var array $prevDescriptor
     */
    private $prevDescriptor;

    /**
     * @var array $nextDescriptor
     */
    private $nextDescriptor;

    /**
     * A single descriptor object in array form
     *
     * @var array $descriptor
     */
    private $descriptor;

    /**
     * Order of the descriptor values in the criterion this descriptor belongs to
     *
     * @var string
     */
    private $direction;


    /**
     * DescriptorMetadata constructor.
     * @param array $descriptor
     * @param array $prevDescriptor     Can be null
     * @param array $nextDescriptor     Can be null
     * @param string $direction
     */
    public function __construct(array $descriptor, ?array $prevDescriptor, ?array $nextDescriptor, string $direction)
    {
        $this->descriptor = $descriptor;
        $this->prevDescriptor = $prevDescriptor;
        $this->nextDescriptor = $nextDescriptor;
        $this->direction = $direction;
    }


    /**
     * A descriptor's minimum value is:
     * - if criterion is ascending, the previous non-null descriptor value + 1
     * - if criterion is descending, the next non-null descriptor value + 1
     *
     * If previous or next descriptor has a null value, use the current descriptor value (which may also be null...)
     *
     * @return float|null
     */
    public function calculateMin(): ?float
    {
        // Assume ascending by default
        $min = ($this->prevDescriptor['value'] !== null ? $this->prevDescriptor['value'] + 1 : $this->descriptor['value']);
        if ($this->direction === CriterionMetadata::DIRECTION_DESCENDING) {
            $min = ($this->nextDescriptor['value'] !== null ? $this->nextDescriptor['value'] + 1 : $this->descriptor['value']);
        }

        return $min;
    }

    /**
     * The persisted value of a descriptor is always its maximum value.
     * May be null.
     *
     * @return float|null
     */
    public function calculateMax(): ?float
    {
        return $this->descriptor['value'];
    }

    /**
     * Min and max values for this descriptor.
     *
     * @return array
     */
    public function toArray(): ?array
    {
        $min = $this->calculateMin();
        $max = $this->calculateMax();

        if ($min === null || $max === null) {
            return null;
        }

        return ['min' => $min, 'max' => $max];
    }
}
