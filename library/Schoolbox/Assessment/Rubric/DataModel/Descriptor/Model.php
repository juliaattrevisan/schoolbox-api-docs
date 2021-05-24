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
    private $indicator;

    /**
     * @var LevelModel
     */
    private $level;

    /**
     * @var float
     */
    private $maxValue;

    /**
     * @var string
     */
    private $description;

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
     * @param  string $description
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return LevelModel
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * @param LevelModel $level
     * @return static
     */
    public function setLevel(LevelModel $level)
    {
        $this->level = $level;
        return $this;
    }

    /**
     * @param  float|null $maxValue
     * @return $this
     */
    public function setMaxValue($maxValue)
    {
        $this->maxValue = $maxValue;
        return $this;
    }

    /**
     * @return float|null
     */
    public function getMaxValue()
    {
        return $this->maxValue;
    }
}
