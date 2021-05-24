<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
        return !empty($maxValues) ? array_sum($maxValues) : 0;
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
     * Marks will only show if:
     * * the max value for this capability is greater than zero
     * * if the assessment's mark-per-capability field is on
     *   (this is not knowable by this object:
     *   @see \Schoolbox\Assessment\Rubric\Grid\Grid::isCapabilityMarkShown)
     *
     * @return bool
     */
    public function isCapabilityMarkShown(): bool
    {
        return $this->maxValue > 0;
    }


    /**
     * Are marks given to capability usage `$other` mutually compatible with
     * ones given to this usage?
     *
     * @param CapabilityUsage $other
     * @return bool
     */
    public function isCompatibleWith(CapabilityUsage $other): bool
    {
        // Check it's the same capability
        if ((int)$this->capability->getId() !== (int)$other->capability->getId()) {
            return false;
        }

        // Check that the mark-per-cap value hasn't changed
        if ($this->maxValue !== $other->maxValue) {
            return false;
        }

        // Check the same indicators are in use
        if ($this->retrieveUsedIndicatorIds() !== $other->retrieveUsedIndicatorIds()) {
            return false;
        }

        // Now we know the indicators in use are the same.
        // Check each usage is compatible with the usage in the other.
        foreach ($this->indicatorUsages as $thisIndicatorUsage) {
            if (
                !$thisIndicatorUsage->isCompatibleWith(
                    $other->retrieveIndicatorUsage($thisIndicatorUsage->getIndicator())
                )
            ) {
                return false;
            }
        }

        return true;
    }


    /**
     * Retrieves the indicator IDs that this capability uses.
     *
     * @return array|int[]
     */
    private function retrieveUsedIndicatorIds(): array
    {
        $indicatorIds = [];
        foreach ($this->indicatorUsages as $indicatorUsage) {
            $indicatorIds[] = (int)$indicatorUsage->getIndicator()->getId();
        }
        sort($indicatorIds);
        return $indicatorIds;
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
