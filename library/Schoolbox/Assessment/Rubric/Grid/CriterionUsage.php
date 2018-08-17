<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

use Schoolbox\Assessment\Rubric\RowSet\Criterion;
use Schoolbox\GenericCollection;

/**
 * Usage of a criterion within a rubric grid.
 *
 * Represented on the rubric grid as a single row.
 */
class CriterionUsage
{
    /**
     * @var Criterion
     */
    private $criterion;


    /**
     * @var GenericCollection|Descriptor[]
     */
    private $descriptors;


    /**
     * Constructor
     *
     * @param Criterion $criterion
     */
    public function __construct(Criterion $criterion)
    {
        $this->criterion   = $criterion;
        $this->descriptors = new GenericCollection(Descriptor::class);
    }


    /**
     * @return Criterion
     */
    public function getCriterion(): Criterion
    {
        return $this->criterion;
    }


    /**
     * What's the max value for this criterion in the grid?
     *
     * Inferred from its descriptors; the highest value descriptor is the max
     * value.
     *
     * @return float|null
     */
    public function calculateMaxValue(): ?float
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
