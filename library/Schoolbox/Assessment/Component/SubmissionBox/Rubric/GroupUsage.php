<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\GenericCollection;

/**
 * Model for usage of a rubric group on a due work.
 */
class GroupUsage
{
    /**
     * @var Group
     */
    private $group;


    /**
     * @var SubmissionBox
     */
    private $submissionBox;


    /**
     * @var float|null
     */
    private $maxValue;


    /**
     * 0-based
     *
     * @var int
     */
    private $sequence = 0;


    /**
     * @var CriterionUsage[]|GenericCollection
     */
    private $criterionUsages;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->criterionUsages = new GenericCollection(CriterionUsage::class);
    }


    /**
     * @return Group
     */
    public function getGroup(): Group
    {
        return $this->group;
    }


    /**
     * @param Group $group
     * @return static
     */
    public function setGroup(Group $group): self
    {
        $this->group = $group;
        return $this;
    }


    /**
     * @return SubmissionBox
     */
    public function getSubmissionBox(): SubmissionBox
    {
        return $this->submissionBox;
    }


    /**
     * @param SubmissionBox $submissionBox
     * @return static
     */
    public function setSubmissionBox(SubmissionBox $submissionBox): self
    {
        $this->submissionBox = $submissionBox;
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
    public function setMaxValue(float $maxValue): self
    {
        $this->maxValue = $maxValue;
        return $this;
    }


    /**
     * @return int
     */
    public function getSequence(): int
    {
        return $this->sequence;
    }


    /**
     * @param int $sequence
     * @return static
     */
    public function setSequence(int $sequence): self
    {
        $this->sequence = $sequence;
        return $this;
    }


    /**
     * @return CriterionUsage[]|GenericCollection
     */
    public function getCriterionUsages(): GenericCollection
    {
        return $this->criterionUsages;
    }


    /**
     * @param CriterionUsage[]|GenericCollection $criterionUsages
     * @return static
     */
    public function setCriterionUsages(GenericCollection $criterionUsages): self
    {
        $this->criterionUsages = $criterionUsages;
        return $this;
    }
}
