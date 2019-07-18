<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel;

use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Model;
use Schoolbox\Mapper\Traits\Model\Id;


/**
 * Model for marks on a rubric group.
 */
class GroupMark
{
    use Id;


    /**
     * @var null|ReturnedWork
     */
    private $submissionReturn;


    /**
     * @var Model
     */
    private $group;


    /**
     * @var bool
     */
    private $valid;


    /**
     * @var float|null
     */
    private $value;


    /**
     * @return null|ReturnedWork
     */
    public function getSubmissionReturn(): ?ReturnedWork
    {
        return $this->submissionReturn;
    }


    /**
     * @param null|ReturnedWork $submissionReturn
     * @return $this
     */
    public function setSubmissionReturn(?ReturnedWork $submissionReturn): self
    {
        $this->submissionReturn = $submissionReturn;
        return $this;
    }


    /**
     * @return Model
     */
    public function getGroup(): Model
    {
        return $this->group;
    }


    /**
     * @param Model $group
     * @return static
     */
    public function setGroup(Model $group): self
    {
        $this->group = $group;
        return $this;
    }


    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->valid;
    }


    /**
     * @deprecated Mapper method only
     * @return bool
     */
    public function getValid(): bool
    {
        return $this->isValid();
    }


    /**
     * @param boolean $valid
     * @return static
     */
    public function setValid($valid): self
    {
        $this->valid = $valid;
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
