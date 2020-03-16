<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
    private const PRECISION = 2;

    /**
     * The feedback which this marked grid is assigned to.
     *
     * @var IFeedback
     */
    private $feedback;


    /**
     * The grid that is used as the template for marking.
     *
     * @var Grid
     */
    private $grid;


    /**
     * The scheme via which the marked grid is marked. This controls how the
     * overall mark for this grid is validated and rendered.
     *
     * TODO this belongs on the grid, not the marked grid
     *
     * @var IMarkScheme
     */
    private $markScheme;


    /**
     * The marks for each capability on the grid.
     *
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
     * Calculates the grid's mark, with the scaling factor from each of its
     * capabilities applied.
     *
     * @return null|float
     */
    public function calculateScaledMark(): ?float
    {
        $value = null;
        foreach ($this->capabilityMarks as $capabilityMark) {
            // Mark-per-capability?
            // Use the actual input value (which may be null) instead of the
            // calculated mark.
            $capabilityValue = $this->grid->isMarkPerCapability()
                ? $capabilityMark->getValue()
                : $capabilityMark->calculateScaledMark();

            if (isset($capabilityValue)) {
                $value = ($value ?? 0) + $capabilityValue;
            }
        }
        return $value;
    }

    /**
     * Convert the given mark to a percentage of the total available marks -
     * this is used by IMarkScheme implementations to generate human readable marks
     *
     * @return null|float
     */
    public function retrieveNormalizedMark(): ?float
    {
        $scaledMark = $this->calculateScaledMark();
        if (!isset($scaledMark)) {
            return null;
        }
        if ($this->calculateTotalAvailableMarks() > 0) {
            return round(($scaledMark / $this->calculateTotalAvailableMarks()) * 100, self::PRECISION);
        }
        return 0.0;
    }

    /**
     * Calculate the highest possible marks that can be given for this rubric, based on the capability maxes
     * which default to the indicator max if mark per cap is off.
     * @return float
     */
    public function calculateTotalAvailableMarks() : float
    {
        // Ignore max value for cap usage, if it's not mark per capability
        $useMarkPerCapability = $this->grid->isMarkPerCapability();
        $totalAvailableMarks  = 0.0;
        foreach ($this->iterateCapabilityUsages() as $capabilityUsage) {
            $totalAvailableMarks +=
                $useMarkPerCapability
                    ? $capabilityUsage->getMaxValue()
                    : $capabilityUsage->calculateMaxValueFromIndicators();
        }
        return $totalAvailableMarks;
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
