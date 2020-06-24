<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
     * Descriptor directionality across the entire set
     */
    private const ORDER_DESCENDING = 0;
    private const ORDER_ASCENDING = 1;


    /**
     * Presume mark ranges step in these amounts
     */
    private const MARK_RANGE_INCREMENT = 1;

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

        /* Filter out null-valued descriptors
         * These don't contribute to ranged values and are skipped
         */
        $temp = [];
        $pureDescriptorValues = [];

        foreach ($this->descriptors as $descriptor) {
            if (null !== $descriptor->getMaxValue()) {
                $temp[] = $descriptor;

                /* Remember the current descriptor's value
                 *
                 * We'll end up using this to determine the data set's overall directionality
                 * which will let us make some bold assumptions for where to mangle the minValue()
                 * to fulfil SA-7628
                 */
                $pureDescriptorValues[] = $descriptor->getMaxValue();
            }
        }

        /* Make a big assumption about $pureDescriptorValues and descriptor directionality overall
         * If sort($pureDescriptorValues) === $pureDescriptorValues, then $pureDescriptorValues
         * was in ascending order
         *
         * Otherwise, it's in descending order, or a bed of chaos like [0,11,4,76,3,21,5] which has no
         * meaning in the current usage of mark-range rubrics (it's an illegal use case despite being catalogued
         * and unit tested in back end). For both, assume it can be classified as descending.
         */
        $direction = self::ORDER_DESCENDING;

        if ($useValueRanges) {
            $pureDescriptorValuesTest = $pureDescriptorValues;
            sort($pureDescriptorValuesTest);

            if ($pureDescriptorValues === $pureDescriptorValuesTest) {
                $direction = self::ORDER_ASCENDING;
            }
        }

        /* Traverse each element in order and look ahead to see what the next
         * descriptor value might be and infer a local direction in each descriptor
         * This will be used to order select dropdowns' values in grid-markable
         * as well as whatever it used to do with retrieveLeftValue retrieveRightValue
         *
         * Catalog a min/max value accordingly so we can easily map it back
         */
        $n = \count($temp);

        for ($i = 0; $i < $n; $i++) {
            $nextDescriptor = $temp[$i+1] ?? null;
            $descriptor = $temp[$i];
            $previousDescriptor = $temp[$i-1] ?? null;

            /* If you aren't using mark ranges, min = max and get out of here
             */
            if (!$useValueRanges) {
                $descriptor->setMinValue($descriptor->getMaxValue());
                continue;
            }

            /* Useful flags
             */
            $isFirstElement = 0 === $i;
            $isLastElement = $i === $n-1;

            /* If this was the first element in the list, or the last element in the list,
             * set its min value to be the same as its max (addresses 1-element lists)
             */
            if ($isFirstElement || $isLastElement) {
                $descriptor->setMinValue($descriptor->getMaxValue());
            }

            /* Map it all back to each of the non-null descriptors in $this->descriptor
             * That should total three linear traversals at most,
             * instead of multiple nested traversals for each descriptor - one left, and one right
             *
             * Objects were passed by reference in $temp so I don't even need to do much more
             */
            if ($nextDescriptor) {
                if ($nextDescriptor->getMaxValue() > $descriptor->getMaxValue()) {

                    /* Since we're ascending we need to recall what we previously saw
                     * But don't let it exceed the current element's maximum
                     *
                     * Occurs when previous descriptor value is the same as the current descriptor value
                     */
                    if ($previousDescriptor) {
                        $newValue = $previousDescriptor->getMaxValue() + self::MARK_RANGE_INCREMENT;

                        $descriptor->setMinValue($newValue > $descriptor->getMaxValue() ? $descriptor->getMaxValue() : $newValue);
                    }
                } elseif ($nextDescriptor->getMaxValue() < $descriptor->getMaxValue()) {
                    $descriptor->setMinValue($nextDescriptor->getMaxValue() + self::MARK_RANGE_INCREMENT);
                }
            } elseif ($previousDescriptor) {

                /* Last element in the list in ascending order
                 */
                if ($previousDescriptor->getMinValue() < $descriptor->getMaxValue()) {
                    $descriptor->setMinValue($previousDescriptor->getMaxValue() + self::MARK_RANGE_INCREMENT);
                }
            }

            /* Assign this directionality to every descriptor in the set
             */
            $descriptor->setDirectionAscending();

            if ($direction === self::ORDER_DESCENDING) {
                $descriptor->setDirectionDescending();
            }
        }

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

//        // Currently, step value is always 1: let this be able to change though
//        $step = 1;
//        $keys = $this->descriptors->keys();
//
//        foreach ($keys as $i => $current) {
//            $descriptor = $this->descriptors[$current];
//            if (!$descriptor) {
//                // something's wrong
//                continue;
//            }
//
//            $maxValue = $descriptor->getMaxValue();
//
//            // Not using value ranges? min = max
//            if (!$useValueRanges) {
//                $descriptor->setMinValue($maxValue);
//                continue;
//            }
//
//            // If current is null: min is null too
//            if (null === $maxValue) {
//                $descriptor->setMinValue(null);
//                continue;
//            }
//
//            $leftMaxValue       = null;
//            $isLeftValueLesser  = false;
//            $rightMaxValue      = null;
//            $isRightValueLesser = false;
//
//            // What's the nearest non-null value to the left of this?
//            $previousNonNullDescriptor = $this->retrieveAdjacentNonNullDescriptor($i, $keys, -1);
//            if ($previousNonNullDescriptor) {
//                $leftMaxValue      = $previousNonNullDescriptor->getMaxValue();
//                $isLeftValueLesser = ($leftMaxValue < $maxValue);
//            }
//
//            // And what's the nearest non-null value to the right?
//            $nextNonNullDescriptor = $this->retrieveAdjacentNonNullDescriptor($i, $keys, 1);
//            if ($nextNonNullDescriptor) {
//                $rightMaxValue      = $nextNonNullDescriptor->getMaxValue();
//                $isRightValueLesser = ($rightMaxValue < $maxValue);
//            }
//
//            // Set the min value based on the left and right values
//            if ($isLeftValueLesser || $isRightValueLesser) {
//                // Either the left or right value is less than this value:
//                // that means the min value is dependent on the left or right.
//
//                if ($isLeftValueLesser && $isRightValueLesser) {
//                    // It should never happen that both left and right value
//                    // are less than this value: that means that I created a
//                    // descriptor sequence that both ascends and descends,
//                    // which is invalid.
//                    // Nonetheless: attempt to handle this case by using the
//                    // greater of left and right.
//                    if ($leftMaxValue > $rightMaxValue) {
//                        $floorValue = $leftMaxValue;
//                        $descriptor->setDirectionAscending();
//                    } else {
//                        $floorValue = $rightMaxValue;
//                        $descriptor->setDirectionDescending();
//                    }
//                } elseif ($isLeftValueLesser) {
//                    // Left value is less than this value: use left value as
//                    // the floor value for this descriptor's min value.
//                    $floorValue = $leftMaxValue;
//                    $descriptor->setDirectionAscending();
//                } else {
//                    // Right value is less than this value: use left value as
//                    // the floor value for this descriptor's min value.
//                    $floorValue = $rightMaxValue;
//                    $descriptor->setDirectionDescending();
//                }
//
//                // Don't allow to be greater than max
//                // (in case validation allowed decimals for ranged)
//                $minValue = min($floorValue + $step, $maxValue);
//            } else {
//                // Neither left or right value are less than this value:
//                // this is not a ranged descriptor.
//                $minValue = $maxValue;
//            }
//
//            $descriptor->setMinValue($minValue);
//        }
    }


//    /**
//     * @param int   $currentKey
//     * @param array $keys
//     * @param int   $increment
//     * @return null|Descriptor
//     */
//    private function retrieveAdjacentNonNullDescriptor(int $currentKey, array $keys, int $increment): ?Descriptor
//    {
//        $adjacentNonNullDescriptor = null;
//        $adjacentKey               = $currentKey + $increment;
//        while (null === $adjacentNonNullDescriptor) {
//            if (!array_key_exists($adjacentKey, $keys)) {
//                break;
//            }
//            $adjacentDescriptor = $this->descriptors[$keys[$adjacentKey]] ?? null;
//            if ($adjacentDescriptor && null !== $adjacentDescriptor->getMaxValue()) {
//                $adjacentNonNullDescriptor = $adjacentDescriptor;
//                break;
//            }
//            $adjacentKey += $increment;
//        }
//        return $adjacentNonNullDescriptor;
//    }


    /**
     * Are marks given to indicator usage `$other` mutually compatible with
     * ones given to this usage?
     *
     * @param IndicatorUsage $other
     * @return bool
     */
    public function isCompatibleWith(IndicatorUsage $other): bool
    {
        // Check it's the same indicator
        if ((int)$this->indicator->getId() !== (int)$other->indicator->getId()) {
            return false;
        }

        // Ensure same descriptors are in use (ordering doesn't matter)
        $thisDescriptorIds  = $this->descriptors->getPropertyValues('id');
        $otherDescriptorIds = $other->descriptors->getPropertyValues('id');
        sort($thisDescriptorIds);
        sort($otherDescriptorIds);
        if ($thisDescriptorIds !== $otherDescriptorIds) {
            return false;
        }

        // Were any new descriptors (which don't have IDs yet) added or removed?
        // That automatically means they're not mutually compatible, even if
        // they would otherwise match.
        if (\in_array(null, array_merge($thisDescriptorIds, $otherDescriptorIds), true)) {
            return false;
        }

        // Ensure each descriptor's values match
        foreach ($thisDescriptorIds as $descriptorId) {
            /** @var Descriptor $thisDescriptor */
            /** @var Descriptor $otherDescriptor */
            $thisDescriptor  = $this->descriptors->getBy('id', $descriptorId);
            $otherDescriptor = $other->descriptors->getBy('id', $descriptorId);
            if ($thisDescriptor->getMaxValue() !== $otherDescriptor->getMaxValue()) {
                return false;
            }
        }

        // If it passes that gauntlet:
        return true;
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
