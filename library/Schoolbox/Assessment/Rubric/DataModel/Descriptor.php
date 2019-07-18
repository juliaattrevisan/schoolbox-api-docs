<?php
/**
 * Model for rubric descriptors
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel;

use Schoolbox\Assessment\Rubric\DataModel\Indicator\Model;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;

class Descriptor implements ISoftDeletable
{
    use Id;
    use DeletedAtDate;

    /**
     * @var Model
     */
    private $criterion;

    /**
     * @var Level
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
     * @param  Model $criterion
     * @return $this
     */
    public function setCriterion(Model $criterion)
    {
        $this->criterion = $criterion;
        return $this;
    }

    /**
     * @return Model
     */
    public function getCriterion()
    {
        return $this->criterion;
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
     * @return Level
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * @param Level $level
     * @return static
     */
    public function setLevel(Level $level)
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
