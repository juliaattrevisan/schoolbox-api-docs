<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid;

use Schoolbox\Assessment\Rubric\Grid\CapabilityUsage;
use Schoolbox\Assessment\Rubric\Grid\IndicatorUsage;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\MarkScheme\IMarkScheme;

/**
 * Marking of a capability within a marked rubric grid.
 *
 * Represented on the marked grid as a collection of rows.
 */
class CapabilityMark
{
    private const PRECISION = 2;

    /**
     * The capability usage for which this mark is for.
     *
     * @var CapabilityUsage
     */
    private $capabilityUsage;


    /**
     * The scheme via which the capability is marked. This controls how the
     * overall mark for the capability is validated and rendered.
     *
     * TODO this belongs on the capability usage, not the capability
     *
     * @var IMarkScheme
     */
    private $markScheme;


    /**
     * The marks for each indicator within the capability.
     *
     * @var GenericCollection|IndicatorMark[]
     */
    private $indicatorMarks;


    /**
     * The value that was given for this capability.
     *
     * This is a raw value, and is not scaled based on the capability usage's
     * max mark, or the mark scheme: it is instead out of the capability
     * usage's maximum value based on its indicators.
     *
     * @var float|null
     */
    private $value;


    /**
     * Constructor
     *
     * @param CapabilityUsage $capabilityUsage
     * @param IMarkScheme     $markScheme
     */
    public function __construct(CapabilityUsage $capabilityUsage, IMarkScheme $markScheme)
    {
        $this->capabilityUsage = $capabilityUsage;
        $this->markScheme      = $markScheme;
        $this->indicatorMarks  = new GenericCollection(IndicatorMark::class);
    }


    /**
     * Calculates the capability's mark, with a scaling factor applied.
     *
     * @return float
     */
    public function calculateScaledMark(): float
    {
        // If a capability mark has been provided, use it
        if (null !== $this->value) {
            return $this->value;
        }

        // Otherwise, just use the given marks directly
        return $this->calculateIndicatorMarkSum();
    }


    /**
     * Calculates the sum of all indicator marks in this capability mark.
     *
     * @return float
     */
    public function calculateIndicatorMarkSum(): float
    {
        $indicatorMarkSum = 0;
        foreach ($this->indicatorMarks as $indicatorMark) {
            $indicatorMarkSum += $indicatorMark->getValue();
        }
        return $indicatorMarkSum;
    }


    /**
     * Gets the sum of all indicator marks and return in the comparable format
     * @return mixed
     */
    public function getIndicatorMarkSumScaled()
    {
        $maxCapabilityValue = $this->getCapabilityUsage()->getMaxValue();
        $rowMaxSum = $this->capabilityUsage->calculateMaxValueFromIndicators();
        return $this->markScheme->getValueForJS($this->calculateIndicatorMarkSum(), $maxCapabilityValue, $rowMaxSum);
    }


    /**
     * Render the capability's mark in normalized format.
     *
     * @return float
     */
    public function retrieveNormalizedMark(): float
    {
        $maxCapabilityValue = $this->getCapabilityUsage()->getMaxValue();
        $normMark = $maxCapabilityValue ? ($this->value / $maxCapabilityValue) * 100 : 0;
        return round($normMark, self::PRECISION);
    }

    /**
     * Render the capability's mark in human-readable format.
     *
     * @return null|string
     */
    public function retrieveHumanReadableMark(): ?string
    {
        return $this->markScheme->getHumanReadableMark($this->retrieveNormalizedMark());
    }


    /**
     * @return CapabilityUsage
     */
    public function getCapabilityUsage(): CapabilityUsage
    {
        return $this->capabilityUsage;
    }


    /**
     * @return IMarkScheme
     */
    public function getMarkScheme(): IMarkScheme
    {
        return $this->markScheme;
    }


    /**
     * Iterates the list of indicator usages?
     *
     * @return iterable|IndicatorUsage[]
     */
    public function iterateIndicatorUsages(): iterable
    {
        foreach ($this->indicatorMarks as $indicatorMark) {
            yield $indicatorMark->getIndicatorUsage();
        }
    }


    /**
     * Retrieve a capability mark for a capability usage, if one exists.
     *
     * @param IndicatorUsage $indicatorUsage
     * @return null|IndicatorMark
     */
    public function retrieveIndicatorMark(IndicatorUsage $indicatorUsage): ?IndicatorMark
    {
        foreach ($this->indicatorMarks as $indicatorMark) {
            // TODO indicator usage should have its own ID
            if ($indicatorMark->getIndicatorUsage()->getIndicator()->getId()
                === $indicatorUsage->getIndicator()->getId()
            ) {
                return $indicatorMark;
            }
        }
        return null;
    }


    /**
     * @return GenericCollection|IndicatorMark[]
     */
    public function getIndicatorMarks(): GenericCollection
    {
        return $this->indicatorMarks;
    }


    /**
     * @param GenericCollection|IndicatorMark[] $indicatorMarks
     * @return static
     */
    public function setIndicatorMarks(GenericCollection $indicatorMarks): self
    {
        $this->indicatorMarks = $indicatorMarks;
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
