<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel;

use Schoolbox\Mapper\Traits\Model\Id;


/**
 * Class DescriptorMark
 *
 * Semantically, this class should really be named Selection or similar, but for the sake of matching the db table,
 * mark it is...
 *
 * @package Schoolbox\Assessment\Rubric\DataModel
 */
class DescriptorMark
{
    use Id;


    /**
     * @var null|GroupMark
     */
    private $groupMark;


    /**
     * @var null|Model
     */
    private $descriptor;


    /**
     * @var bool
     */
    private $valid;


    /**
     * @var float|null
     */
    private $value;


    /**
     * @return null|Model
     */
    public function getDescriptor(): ?Model
    {
        return $this->descriptor;
    }


    /**
     * @param null|Model $descriptor
     * @return static
     */
    public function setDescriptor(?Model $descriptor): self
    {
        $this->descriptor = $descriptor;
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


    /**
     * @return null|GroupMark
     */
    public function getGroupMark(): ?GroupMark
    {
        return $this->groupMark;
    }


    /**
     * @param null|GroupMark $groupMark
     * @return static
     */
    public function setGroupMark(?GroupMark $groupMark): self
    {
        $this->groupMark = $groupMark;
        return $this;
    }
}
