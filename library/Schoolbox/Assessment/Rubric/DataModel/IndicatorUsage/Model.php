<?php

/**
 * Model for rubric indicator usage.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel\IndicatorUsage;

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Model as IndicatorModel;

class Model
{

    /** @var int */
    public const SEQUENCE_BASE = 1; // Indicator sequence is 1-based, not 0-based

    /**
     * @var IndicatorModel
     */
    private $indicator;

    /**
     * @var SubmissionBox
     */
    private $submission_box;

    /**
     * This indicator usage's max value.
     */
    private ?float $maxValue = null;

    /**
     * @var int
     */
    private $sequence;

    /**
     * @param IndicatorModel $indicator
     * @return $this
     */
    public function setIndicator(IndicatorModel $indicator)
    {
        $this->indicator = $indicator;
        return $this;
    }

    /**
     * @return IndicatorModel
     */
    public function getIndicator()
    {
        return $this->indicator;
    }

    /**
     * @param  SubmissionBox $submission_box
     * @return $this
     */
    public function setSubmissionBox(SubmissionBox $submission_box)
    {
        $this->submission_box = $submission_box;
        return $this;
    }

    /**
     * @return SubmissionBox
     */
    public function getSubmissionBox()
    {
        return $this->submission_box;
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


    /**
     * @return int|null
     */
    public function getSequence(): ?int
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
}
