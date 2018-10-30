<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

use Schoolbox\Assessment\Rubric\RowSet\Indicator;
use Schoolbox\GenericCollection;

/**
 * Usage of a indicator within a rubric grid.
 *
 * Represented on the rubric grid as a single row.
 */
class IndicatorUsage
{
    /**
     * @var Indicator
     */
    private $indicator;


    /**
     * @var GenericCollection|Descriptor[]
     */
    private $descriptors;


    /**
     * Constructor
     *
     * @param Indicator $indicator
     */
    public function __construct(Indicator $indicator)
    {
        $this->indicator   = $indicator;
        $this->descriptors = new GenericCollection(Descriptor::class);
    }


    /**
     * Return the descriptor for a given level, or null if this indicator
     * doesn't use it.
     *
     * @param int $levelIndex
     * @return null|Descriptor
     */
    public function retrieveDescriptor(int $levelIndex): ?Descriptor
    {
        return $this->descriptors[$levelIndex] ?? null;
    }


    /**
     * What's the max value for this indicator in the grid?
     *
     * Inferred from its descriptors; the highest value descriptor is the max
     * value.
     *
     * @return float|null
     */
    public function calculateMaxValueFromDescriptors(): ?float
    {
        $values = [];
        foreach ($this->descriptors as $descriptor) {
            $descriptorMaxValue = $descriptor->getMaxValue();
            if (null !== $descriptorMaxValue) {
                $values[] = $descriptorMaxValue;
            }
        }
        return !empty($values) ? max($values) : null;
    }


    /**
     * Does this indicator usage have any descriptors which have a value?
     *
     * @return bool
     */
    public function containsDescriptorsWithValue(): bool
    {
        return ($this->calculateMaxValueFromDescriptors() ?? 0) > 0;
    }


    /**
     * Apply the min values for all the descriptors in this indicator.
     *
     * @param bool $useValueRanges Does the rubric grid this is a part of even
     *                             use value ranges?
     *                             If not, min value is always max value.
     */
    public function applyDescriptorMinValues(bool $useValueRanges): void
    {
        // The rules for min-max values, from SA-6627:
        // * The highest possible value for a descriptor is the points value
        //   supplied for that descriptor at rubric creation time
        // * If the points values are ascending (the first descriptor has the
        //   lowest points value of any descriptor in the row):
        // ** The lowest possible value for the descriptor is the points value
        //    supplied for the previous descriptor (to the left), plus 1
        // ** If there is no descriptor to the left, the lowest possible value
        //    is also the highest possible value
        // * Otherwise, if the points values are descending (the first
        //   descriptor has the highest points value of any descriptor in the
        //   row):
        // ** The lowest possible value for the descriptor is the points value
        //    supplied for the next descriptor to the right, plus 1
        // ** If there is no descriptor to the right, the lowest possible value
        //    is also the highest possible value
        // * The user is able to select any integer value between the lowest
        //   and highest values inclusive
        //
        // My own rule:
        // * If the points values both ascend and descend: the min values are
        //   undefined

        // Currently, step value is always 1: let this be able to change though
        $step = 1;
        $keys = $this->descriptors->keys();
        foreach ($keys as $i => $current) {
            $descriptor = $this->descriptors[$current];
            if (!$descriptor) {
                // something's wrong
                continue;
            }

            $maxValue = $descriptor->getMaxValue();

            // Not using value ranges? min = max
            if (!$useValueRanges) {
                $descriptor->setMinValue($maxValue);
                continue;
            }

            // If current is null: min is null too
            if (null === $maxValue) {
                $descriptor->setMinValue(null);
                continue;
            }

            $leftMaxValue  = null;
            $rightMaxValue = null;

            // What's the nearest non-null value to the left of this?
            $previousNonNullDescriptor = $this->retrieveAdjacentNonNullDescriptor($i, $keys, -1);
            if ($previousNonNullDescriptor && $previousNonNullDescriptor->getMaxValue() < $maxValue) {
                $leftMaxValue = $previousNonNullDescriptor->getMaxValue();
            }

            // And what's the nearest non-null value to the right?
            $nextNonNullDescriptor = $this->retrieveAdjacentNonNullDescriptor($i, $keys, 1);
            if ($nextNonNullDescriptor && $nextNonNullDescriptor->getMaxValue() < $maxValue) {
                $rightMaxValue = $nextNonNullDescriptor->getMaxValue();
            }

            // No candidates: use max value
            $minValue = $maxValue;
            if ($leftMaxValue !== null || $rightMaxValue !== null) {
                // The greater of left and right gets used.
                // Left:  ascending
                // Right: descending
                if ($leftMaxValue < $rightMaxValue) {
                    $floorValue = $rightMaxValue;
                    $descriptor->setDirectionDescending();
                } else {
                    $floorValue = $leftMaxValue;
                    $descriptor->setDirectionAscending();
                }

                // Don't allow to be greater than max
                // (in case validation allowed decimals for ranged)
                $minValue = min($floorValue + $step, $maxValue);
            }
            $descriptor->setMinValue($minValue);
        }
    }


    /**
     * @param int   $currentKey
     * @param array $keys
     * @param int   $increment
     * @return null|Descriptor
     */
    private function retrieveAdjacentNonNullDescriptor(int $currentKey, array $keys, int $increment): ?Descriptor
    {
        $adjacentNonNullDescriptor = null;
        $adjacentKey               = $currentKey + $increment;
        while (null === $adjacentNonNullDescriptor) {
            if (!array_key_exists($adjacentKey, $keys)) {
                break;
            }
            $adjacentDescriptor = $this->descriptors[$keys[$adjacentKey]] ?? null;
            if ($adjacentDescriptor && null !== $adjacentDescriptor->getMaxValue()) {
                $adjacentNonNullDescriptor = $adjacentDescriptor;
                break;
            }
            $adjacentKey += $increment;
        }
        return $adjacentNonNullDescriptor;
    }


    /**
     * @return Indicator
     */
    public function getIndicator(): Indicator
    {
        return $this->indicator;
    }


    /**
     * @return GenericCollection|Descriptor[]
     */
    public function getDescriptors(): GenericCollection
    {
        return $this->descriptors;
    }


    /**
     * @param GenericCollection|Descriptor[] $descriptors
     * @return static
     */
    public function setDescriptors(GenericCollection $descriptors): self
    {
        $this->descriptors = $descriptors;
        return $this;
    }
}
