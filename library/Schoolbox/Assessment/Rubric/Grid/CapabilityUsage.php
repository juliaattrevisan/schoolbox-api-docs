<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

use Schoolbox\Assessment\Rubric\RowSet\Indicator;
use Schoolbox\Assessment\Rubric\RowSet\Capability;
use Schoolbox\GenericCollection;

/**
 * Usage of a group within a rubric grid.
 *
 * Represented on the rubric grid as a collection of rows.
 */
class CapabilityUsage
{
    /**
     * @var Capability
     */
    private $capability;


    /**
     * @var GenericCollection|IndicatorUsage[]
     */
    private $indicatorUsages;


    /**
     * The max value allowed to be set for this group in the grid.
     *
     * @var float|null
     */
    private $maxValue;


    /**
     * Constructor
     *
     * @param Capability $group
     */
    public function __construct(Capability $group)
    {
        $this->capability      = $group;
        $this->indicatorUsages = new GenericCollection(IndicatorUsage::class);
    }


    /**
     * Iterates each indicator in this usage object's group, whether in use or
     * not.
     *
     * First iterates all indicators in use, then all indicators not in use.
     *
     * @return iterable|Indicator[]
     */
    public function iterateIndicators(): iterable
    {
        $seenIndicators = [];
        foreach ($this->getIndicatorUsages() as $indicatorUsage) {
            $seenIndicators[$indicatorUsage->getIndicator()->getId()] = true;
            yield $indicatorUsage->getIndicator();
        }

        foreach ($this->getCapability()->getIndicators() as $indicator) {
            if (!isset($seenIndicators[$indicator->getId()])) {
                yield $indicator;
            }
        }
    }


    /**
     * Return the indicator usage for a given indicator, or null if this group
     * doesn't use it.
     *
     * @param Indicator $indicator
     * @return null|IndicatorUsage
     */
    public function retrieveIndicatorUsage(Indicator $indicator): ?IndicatorUsage
    {
        foreach ($this->indicatorUsages as $indicatorUsage) {
            if ($indicatorUsage->getIndicator()->getId() === $indicator->getId()) {
                return $indicatorUsage;
            }
        }
        return null;
    }


    /**
     * What's the inferred max value for this group?
     *
     * Differs from `getMaxValue` in that this is the inferred value from this
     * group's indicators, instead of the actually set value.
     *
     * This value will never be less than 1, as it's used in order to scale the
     * capability mark; if it were zero, capability marks would always be zero.
     *
     * @return float
     */
    public function calculateMaxValueFromIndicators(): float
    {
        $maxValues = [];
        foreach ($this->indicatorUsages as $indicatorUsage) {
            $maxValue = $indicatorUsage->calculateMaxValueFromDescriptors();
            if (null !== $maxValue) {
                $maxValues[] = $maxValue;
            }
        }
        return max(1, (!empty($maxValues) ? array_sum($maxValues) : 0));
    }


    /**
     * Calculates the mark multiplier for this capability usage.
     *
     * This is the multiplier by which marks for this capability are scaled in
     * order to determine their contribution to an overall mark.
     *
     * @example if the max value for this capability is 12, and the max value
     *          of this capability's indicators is 24, then the capability mark
     *          is multiplied by 0.5 to determine its overall contribution.
     *
     * @return float
     */
    public function calculateMarkMultiplier(): float
    {
        $maxIndicatorValue = $this->calculateMaxValueFromIndicators();
        return $maxIndicatorValue ? $this->maxValue / $maxIndicatorValue : 0;
    }


    /**
     * Does this capability usage have any descriptors which have a value?
     *
     * If not, then the interface shouldn't prompt to assign a value from
     * descriptors for this capability.
     *
     * @return bool
     */
    public function containsDescriptorsWithValue(): bool
    {
        foreach ($this->indicatorUsages as $indicatorUsage) {
            if ($indicatorUsage->containsDescriptorsWithValue()) {
                return true;
            }
        }
        return false;
    }


    /**
     * Should capability marks show for this capability usage?
     *
     * Marks will only show if the max value for this capability is greater
     * than zero, and mark-per-capability is on.
     *
     * @return bool
     */
    public function showCapabilityMark(): bool
    {
        return $this->maxValue > 0;
    }


    /**
     * @return Capability
     */
    public function getCapability(): Capability
    {
        return $this->capability;
    }


    /**
     * @return GenericCollection|IndicatorUsage[]
     */
    public function getIndicatorUsages(): GenericCollection
    {
        return $this->indicatorUsages;
    }


    /**
     * @param GenericCollection|IndicatorUsage[] $indicatorUsages
     * @return static
     */
    public function setIndicatorUsages(GenericCollection $indicatorUsages): self
    {
        $this->indicatorUsages = $indicatorUsages;
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
