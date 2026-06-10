<?php

/**
 * Model for rubric descriptors
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel\Descriptor;

use Schoolbox\Assessment\Rubric\DataModel\Indicator\Model as IndicatorModel;
use Schoolbox\Assessment\Rubric\DataModel\Level\Model as LevelModel;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;

class Model implements ISoftDeletable
{
    use Id;
    use DeletedAtDate;

    /**
     * @var IndicatorModel
     */
    private IndicatorModel $indicator;


    /**
     * @var LevelModel
     */
    private LevelModel $level;


    /**
     * @var null|float
     */
    private ?float $maxValue;


    /**
     * @var string
     */
    private string $description;


    /**
     * @return IndicatorModel
     */
    public function getIndicator(): IndicatorModel
    {
        return $this->indicator;
    }


    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }


    /**
     * @param IndicatorModel $indicator
     * @return $this
     */
    public function setIndicator(IndicatorModel $indicator): self
    {
        $this->indicator = $indicator;
        return $this;
    }


    /**
     * @param  string $description
     * @return $this
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }


    /**
     * @return LevelModel
     */
    public function getLevel(): LevelModel
    {
        return $this->level;
    }


    /**
     * @param LevelModel $level
     * @return static
     */
    public function setLevel(LevelModel $level): self
    {
        $this->level = $level;
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
     * @param  float|null $maxValue
     * @return $this
     */
    public function setMaxValue(?float $maxValue): self
    {
        $this->maxValue = $maxValue;
        return $this;
    }
}
