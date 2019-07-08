<?php
/**
 * Model for rubric descriptors
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;

class Descriptor implements ISoftDeletable
{
    use Id;
    use DeletedAtDate;

    /**
     * @var Criterion
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
     * @param  Criterion $criterion
     * @return $this
     */
    public function setCriterion(Criterion $criterion)
    {
        $this->criterion = $criterion;
        return $this;
    }

    /**
     * @return Criterion
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
     * @deprecated use `$this->setMaxValue()` instead
     * @param  float|null $value
     * @return $this
     */
    public function setValue($value)
    {
        trigger_error('This method is deprecated; use "setMaxValue()" instead', E_USER_DEPRECATED);
        $this->setMaxValue($value);
        return $this;
    }

    /**
     * @deprecated use `$this->getMaxValue()` instead
     * @return float|null
     */
    public function getValue()
    {
        trigger_error('This method is deprecated; use "getMaxValue()" instead', E_USER_DEPRECATED);
        return $this->getMaxValue();
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
