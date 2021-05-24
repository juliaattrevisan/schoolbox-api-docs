<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

use DomainException;
use RangeException;
use Schoolbox\Assessment\Rubric\RowSet\Indicator;
use Schoolbox\Assessment\Rubric\RowSet\Capability;
use Schoolbox\Assessment\Rubric\RowSet\RowSet;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * A rubric grid.
 *
 * This is a representation of the rubric grid as assigned to an assessment.
 *
 * It contains:
 * * an assessment to which this is assigned (in practise, always a due work)
 * * a row set
 * * a set of group usages
 * * a set of levels
 *
 * The grid's descriptors are able to be inferred from the grid's level set and
 * group usage set.
 */
class Grid
{
    /**
     * The assessment for which the grid exists.
     * Rubric Grids only exist for DueWork instances, for now.
     *
     * @var DueWork
     */
    private $assessment;


    /**
     * The row set for the grid.
     *
     * @var RowSet
     */
    private $rowSet;


    /**
     * The capabilities that are actually in use on the rubric grid.
     *
     * Each group's indicator usage is a row on the rubric grid.
     *
     * @var CapabilityUsage[]|GenericCollection
     */
    private $capabilityUsages;


    /**
     * The levels in use on the rubric grid.
     *
     * Each level is a column on the rubric grid.
     *
     * @var Level[]|GenericCollection
     */
    private $levels;


    /**
     * Capabilities' values can be represented differently than the assessment's
     *
     * @var MarkSchemeFactory
     */
    private $markSchemeFactory;


    /**
     * Constructor
     *
     * @param DueWork $assessment
     * @param RowSet  $rowSet
     */
    public function __construct(DueWork $assessment, RowSet $rowSet)
    {
        $this->assessment        = $assessment;
        $this->rowSet            = $rowSet;
        $this->capabilityUsages  = new GenericCollection(CapabilityUsage::class);
        $this->levels            = new GenericCollection(Level::class);
        $this->markSchemeFactory = new MarkSchemeFactory();
    }


    /**
     * Retrieve an identifier for this rubric grid.
     *
     * Currently, it's always the assessment's ID; or 0 if it doesn't have an
     * ID yet.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->assessment->getId() ?: 0;
    }


    /**
     * What's the max value for this grid?
     *
     * Inferred from the sum of the non-null max values for its group usages.
     *
     * @return float
     */
    public function calculateMaxValueFromCapabilities(): float
    {
        // Ignore max value for cap usage, if it's not mark per capability
        $useMarkPerCapability = $this->isMarkPerCapability();
        $maxValues            = [];
        foreach ($this->capabilityUsages as $capabilityUsage) {
            $capabilityUsageMaxValue =
                $useMarkPerCapability
                    ? $capabilityUsage->getMaxValue()
                    : $capabilityUsage->calculateMaxValueFromIndicators();
            if (null !== $capabilityUsageMaxValue) {
                $maxValues[] = $capabilityUsageMaxValue;
            }
        }
        return !empty($maxValues) ? array_sum($maxValues) : 0;
    }


    /**
     * Does this rubric grid use mark-per-capability?
     *
     * If your mark type is None, or not set, then you're not using Mark per Capability.
     *
     * @return bool
     */
    public function isMarkPerCapability(): bool
    {
        return $this->assessment instanceof DueWork && $this->assessment->isMarkPerCapability();
    }

    /**
     * Descriptor value display setting from folder year level
     * @return bool
     */
    public function showDescriptorValue(): bool
    {
        if (!$this->assessment instanceof DueWork) {
            return false;
        }
        $folder = $this->assessment->getContext()->getFolder();
        if (!($folder instanceof Folder) || !$folder->getYearLevels() || $folder->getYearLevels()->count() === 0) {
            return true;
        }
        $showDescriptorValue = array_unique($folder->getYearLevels()->getPropertyValues('showRubricPoints'));
        return count($showDescriptorValue) > 1 ? true : (bool)$showDescriptorValue[0];
    }

    /**
     * Return the group usage for a given group, or null if this grid doesn't
     * use it.
     *
     * @param Capability $capability
     * @return null|CapabilityUsage
     */
    public function retrieveCapabilityUsage(Capability $capability): ?CapabilityUsage
    {
        foreach ($this->capabilityUsages as $capabilityUsage) {
            if ($capabilityUsage->getCapability()->getId() === $capability->getId()) {
                return $capabilityUsage;
            }
        }
        return null;
    }


    /**
     * Return the indicator usage for a given indicator, or null if this grid
     * doesn't use it.
     *
     * @param Indicator $indicator
     * @return null|IndicatorUsage
     */
    public function retrieveIndicatorUsage(Indicator $indicator): ?IndicatorUsage
    {
        foreach ($this->capabilityUsages as $capabilityUsage) {
            $indicatorUsage = $capabilityUsage->retrieveIndicatorUsage($indicator);
            if ($indicatorUsage) {
                return $indicatorUsage;
            }
        }
        return null;
    }


    /**
     * Return the indicator usage for a given descriptor ID, or null if this
     * grid doesn't use it.
     *
     * @param int $descriptorId
     * @return null|IndicatorUsage
     */
    public function retrieveIndicatorUsageByDescriptorId(int $descriptorId): ?IndicatorUsage
    {
        // TODO investigate performance, this is 3x O(n)
        foreach ($this->capabilityUsages as $capabilityUsage) {
            foreach ($capabilityUsage->getIndicatorUsages() as $indicatorUsage) {
                if ($indicatorUsage->getDescriptors()->getBy('id', $descriptorId)) {
                    return $indicatorUsage;
                }
            }
        }
        return null;
    }


    /**
     * Iterates each group in the grid's rowset, whether this grid uses them
     * or not.
     *
     * First iterates all capabilities in use, then all capabilities not in use.
     *
     * @return iterable|Capability[]
     */
    public function iterateCapabilities(): iterable
    {
        $seenCapabilities = [];
        foreach ($this->capabilityUsages as $capabilityUsage) {
            $seenCapabilities[$capabilityUsage->getCapability()->getId()] = true;
            yield $capabilityUsage->getCapability();
        }

        foreach ($this->rowSet->getCapabilities() as $capability) {
            if (!isset($seenCapabilities[$capability->getId()])) {
                yield $capability;
            }
        }
    }


    /**
     * Iterates each indicator in the grid for the given group.
     *
     * @param Capability $capability
     * @return iterable|Indicator[]
     */
    public function iterateIndicators(Capability $capability): iterable
    {
        $capabilityUsage = $this->retrieveCapabilityUsage($capability);
        return $capabilityUsage ? $capabilityUsage->iterateIndicators() : $capability->getIndicators();
    }


    /**
     * Should capability marks show for the given capability usage?
     *
     * Marks will only show if:
     * * this grid is on a due work which supports mark-per-capability
     * * the max value for this grid's usage of `$capability` is > 0
     *
     * @param Capability $capability
     * @return bool
     */
    public function isCapabilityMarkShown(Capability $capability): bool
    {
        if (!($this->assessment instanceof DueWork && $this->assessment->isMarkPerCapability())) {
            return false;
        }

        $capabilityUsage = $this->retrieveCapabilityUsage($capability);
        if (!$capabilityUsage instanceof CapabilityUsage) {
            return false;
        }

        return $capabilityUsage->isCapabilityMarkShown();
    }


    /**
     * Are marked grids given to rubric `$other` mutually compatible with ones
     * given to this grid?
     *
     * @param Grid $other
     * @return bool
     */
    public function isCompatibleWith(Grid $other): bool
    {
        // Check the assessment is the same
        if ((int)$this->assessment->getId() !== (int)$other->assessment->getId()) {
            return false;
        }

        // Check that each has the same capabilities in use
        if ($this->retrieveUsedCapabilityIds() !== $other->retrieveUsedCapabilityIds()) {
            return false;
        }

        // Now we know the capabilities in use are the same.
        // Check each usage is compatible with the usage in the other.
        foreach ($this->capabilityUsages as $thisCapabilityUsage) {
            if (
                !$thisCapabilityUsage->isCompatibleWith(
                    $other->retrieveCapabilityUsage($thisCapabilityUsage->getCapability())
                )
            ) {
                return false;
            }
        }

        return true;
    }


    /**
     * Retrieves the capability IDs that this grid uses.
     *
     * @return array|int[]
     */
    private function retrieveUsedCapabilityIds(): array
    {
        $capabilityIds = [];
        foreach ($this->capabilityUsages as $capabilityUsage) {
            $capabilityIds[] = (int)$capabilityUsage->getCapability()->getId();
        }
        sort($capabilityIds);
        return $capabilityIds;
    }


    /**
     * Get an IMarkScheme instance for translation of capability marks specifically,
     * and separately from its parent assessment
     *
     * @return IMarkScheme
     * @throws DomainException
     * @throws RangeException
     */
    public function getCapabilityMarkScheme(): IMarkScheme
    {
        return $this->markSchemeFactory->spawnForCapability($this->getAssessment());
    }


    /**
     * @return DueWork
     */
    public function getAssessment(): DueWork
    {
        return $this->assessment;
    }


    /**
     * @return RowSet
     */
    public function getRowSet(): RowSet
    {
        return $this->rowSet;
    }


    /**
     * @return CapabilityUsage[]|GenericCollection
     */
    public function getCapabilityUsages(): GenericCollection
    {
        return $this->capabilityUsages;
    }


    /**
     * @param CapabilityUsage[]|GenericCollection $capabilityUsages
     * @return static
     */
    public function setCapabilityUsages(GenericCollection $capabilityUsages): self
    {
        $this->capabilityUsages = $capabilityUsages;
        return $this;
    }


    /**
     * @return Level[]|GenericCollection
     */
    public function getLevels(): GenericCollection
    {
        return $this->levels;
    }


    /**
     * @param Level[]|GenericCollection $levels
     * @return static
     */
    public function setLevels(GenericCollection $levels): self
    {
        $this->levels = $levels;
        return $this;
    }
}
