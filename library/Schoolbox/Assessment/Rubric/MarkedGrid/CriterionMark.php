<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid;

use Schoolbox\Assessment\Rubric\Grid\CriterionUsage;

/**
 * Marking of a group within a marked rubric grid.
 *
 * Represented on the marked grid as a single row, which may or may not have a
 * single marked descriptor.
 */
class CriterionMark
{
    /**
     * @var CriterionUsage
     */
    private $criterionUsage;


    /**
     * @var DescriptorMark|null
     */
    private $descriptorMark;


    /**
     * Constructor
     *
     * @param CriterionUsage $criterionUsage
     */
    public function __construct(CriterionUsage $criterionUsage)
    {
        $this->criterionUsage = $criterionUsage;
    }


    /**
     * What's the value for this criterion's mark?
     *
     * @return float|null
     */
    public function calculateValue(): ?float
    {
        if (!$this->descriptorMark) {
            return null;
        }
        return $this->descriptorMark->getValue();
    }


    /**
     * @return CriterionUsage
     */
    public function getCriterionUsage(): CriterionUsage
    {
        return $this->criterionUsage;
    }


    /**
     * @return null|DescriptorMark
     */
    public function getDescriptorMark(): ?DescriptorMark
    {
        return $this->descriptorMark;
    }


    /**
     * @param null|DescriptorMark $descriptorMark
     * @return static
     */
    public function setDescriptorMark(?DescriptorMark $descriptorMark): self
    {
        $this->descriptorMark = $descriptorMark;
        return $this;
    }
}
