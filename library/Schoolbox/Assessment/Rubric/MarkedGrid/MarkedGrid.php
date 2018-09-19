<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid;

use Schoolbox\Assessment\Rubric\Grid\CapabilityUsage;
use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\Assessment\Rubric\Grid\IndicatorUsage;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\MarkScheme\IMarkScheme;

/**
 * A marked rubric grid.
 *
 * This is a representation of the rubric grid as marked for a participant's
 * feedback.
 *
 * It contains:
 * * a feedback to which this is assigned (in practise, always a due work
 *   feedback as the grid may only exist on a due work)
 * * a grid
 * * a set of marked capabilities, which in turn contain marked indicators
 */
class MarkedGrid
{
    /**
     * @var IFeedback
     */
    private $feedback;


    /**
     * @var Grid
     */
    private $grid;


    /**
     * TODO this belongs on the grid, not the marked grid
     *
     * @var IMarkScheme
     */
    private $markScheme;


    /**
     * @var GenericCollection|CapabilityMark[]
     */
    private $capabilityMarks;


    /**
     * Constructor
     *
     * @param IFeedback   $feedback
     * @param Grid        $grid
     * @param IMarkScheme $markScheme
     */
    public function __construct(IFeedback $feedback, Grid $grid, IMarkScheme $markScheme)
    {
        $this->feedback        = $feedback;
        $this->grid            = $grid;
        $this->markScheme      = $markScheme;
        $this->capabilityMarks = new GenericCollection(CapabilityMark::class);
    }


    /**
     * Calculates the grid's mark, with a scaling factor applied.
     *
     * @return float
     */
    public function calculateScaledMark(): float
    {
        $value           = 0;
        $actualMaxValue  = $this->grid->calculateMaxValueFromCapabilities();
        $derivedMaxValue = 0;
        foreach ($this->capabilityMarks as $capabilityMark) {
            $value           += $capabilityMark->getValue();
            $derivedMaxValue += $capabilityMark->getCapabilityUsage()->calculateMaxValueFromIndicators();
        }
        $scalingFactor = $actualMaxValue ? ($derivedMaxValue / $actualMaxValue) : 0;
        return $scalingFactor ? $value / $scalingFactor : 0;
    }


    /**
     * Render the grid's mark in normalized format.
     *
     * @return float
     */
    public function retrieveNormalizedMark(): float
    {
        $capabilityMaxValues = [];
        $totalWeight         = 0;
        foreach ($this->capabilityMarks as $capabilityMark) {
            $capabilityNormMark    = $capabilityMark->retrieveNormalizedMark();
            $capabilityWeight      = $capabilityMark->getCapabilityUsage()->getMaxValue();
            $capabilityMaxValues[] = [$capabilityNormMark, $capabilityWeight,];
            $totalWeight += $capabilityWeight;
        }

        if ($totalWeight === 0) {
            return 0;
        }

        $normalizedMark = 0;
        foreach ($capabilityMaxValues as [$capabilityNormMark, $capabilityWeight]) {
            if ($capabilityWeight === 0) {
                continue;
            }
            $normalizedMark += ($capabilityNormMark * ($capabilityWeight / $totalWeight));
        }
        return $normalizedMark;
    }


    /**
     * Render the grid's mark in human-readable format.
     *
     * @return null|string
     */
    public function retrieveHumanReadableMark(): ?string
    {
        return $this->markScheme->getHumanReadableMark($this->retrieveNormalizedMark());
    }


    /**
     * @return IFeedback
     */
    public function getFeedback(): IFeedback
    {
        return $this->feedback;
    }


    /**
     * @return Grid
     */
    public function getGrid(): Grid
    {
        return $this->grid;
    }


    /**
     * @return IMarkScheme
     */
    public function getMarkScheme(): IMarkScheme
    {
        return $this->markScheme;
    }


    /**
     * Returns an iterator over capability usages.
     *
     * @return iterable|CapabilityUsage[]
     */
    public function iterateCapabilityUsages(): iterable
    {
        return $this->grid->getCapabilityUsages();
    }


    /**
     * Retrieve a capability mark for a capability usage, if one exists.
     *
     * @param CapabilityUsage $capabilityUsage
     * @return null|CapabilityMark
     */
    public function retrieveCapabilityMark(CapabilityUsage $capabilityUsage): ?CapabilityMark
    {
        foreach ($this->capabilityMarks as $capabilityMark) {
            // TODO capability usage should have its own ID
            if ($capabilityMark->getCapabilityUsage()->getCapability()->getId()
                === $capabilityUsage->getCapability()->getId()
            ) {
                return $capabilityMark;
            }
        }
        return null;
    }


    /**
     * Retrieve an indicator mark for an indicator usage, if one exists.
     *
     * @param IndicatorUsage $indicatorUsage
     * @return null|IndicatorMark
     */
    public function retrieveIndicatorMark(IndicatorUsage $indicatorUsage): ?IndicatorMark
    {
        foreach ($this->capabilityMarks as $capabilityMark) {
            $indicatorMark = $capabilityMark->retrieveIndicatorMark($indicatorUsage);
            if ($indicatorMark) {
                return $indicatorMark;
            }
        }
        return null;
    }


    /**
     * @return CapabilityMark[]|GenericCollection
     */
    public function getCapabilityMarks(): GenericCollection
    {
        return $this->capabilityMarks;
    }


    /**
     * @param CapabilityMark[]|GenericCollection $capabilityMarks
     * @return static
     */
    public function setCapabilityMarks(GenericCollection $capabilityMarks): self
    {
        $this->capabilityMarks = $capabilityMarks;
        return $this;
    }
}
