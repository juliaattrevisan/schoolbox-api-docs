<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid;

use Schoolbox\Assessment\Rubric\Grid\CapabilityUsage;
use Schoolbox\Assessment\Rubric\Grid\IndicatorUsage;
use Schoolbox\GenericCollection;

/**
 * Marking of a capability within a marked rubric grid.
 *
 * Represented on the marked grid as a collection of rows.
 */
class CapabilityMark
{
    /**
     * @var CapabilityUsage
     */
    private $capabilityUsage;


    /**
     * @var GenericCollection|IndicatorMark[]
     */
    private $indicatorMarks;


    /**
     * @var float|null
     */
    private $value;


    /**
     * Constructor
     *
     * @param CapabilityUsage $capabilityUsage
     */
    public function __construct(CapabilityUsage $capabilityUsage)
    {
        $this->capabilityUsage = $capabilityUsage;
        $this->indicatorMarks  = new GenericCollection(IndicatorMark::class);
    }


    /**
     * @return CapabilityUsage
     */
    public function getCapabilityUsage(): CapabilityUsage
    {
        return $this->capabilityUsage;
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
     * @return null|CapabilityMark
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
     * @return GenericCollection
     */
    public function getIndicatorMarks(): GenericCollection
    {
        return $this->indicatorMarks;
    }


    /**
     * @param GenericCollection $indicatorMarks
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
