<?php
/**
 * Model for rubric descriptors
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\Id;

class Descriptor implements ISoftDeletable
{
    use Id;

    /**
     * @var Criterion
     */
    private $criterion;

    /**
     * @var Level
     */
    private $level;

    /**
     * @var int
     */
    private $value;

    /**
     * @var string
     */
    private $description;

    /**
     * Soft-delete column
     *
     * @var bool
     */
    private $valid;

    /**
     * @param  Criterion $criterion
     * @return $this
     */
    public function setCriterion($criterion)
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
    public function setLevel($level)
    {
        $this->level = $level;
        return $this;
    }


    /**
     * @param  boolean $valid
     * @return $this
     */
    public function setValid($valid)
    {
        $this->valid = $valid;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getValid()
    {
        return $this->valid;
    }

    /**
     * @param  int $value
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @return int
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return bool
     */
    public function isDeleted()
    {
        return !$this->getValid();
    }
}
