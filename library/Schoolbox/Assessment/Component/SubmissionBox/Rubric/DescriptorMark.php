<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Mapper\Traits\Model\Id;


/**
 * Class DescriptorMark
 *
 * Semantically, this class should really be named Selection or similar, but for the sake of matching the db table,
 * mark it is...
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\Rubric
 */
class DescriptorMark
{
    use Id;


    /**
     * @var null|GroupMark
     */
    private $groupMark;


    /**
     * @var null|Descriptor
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
     * @deprecated use `getGroupMark()->getSubmissionReturn()` instead
     * @return null|ReturnedWork
     */
    public function getSubmissionReturn(): ?ReturnedWork
    {
        trigger_error(
            'This method is deprecated; use "getGroupMark()->getSubmissionReturn()" instead',
            E_USER_DEPRECATED
        );
//        return $this->submissionReturn;
        return $this->groupMark->getSubmissionReturn();
    }


    /**
     * @deprecated use `getGroupMark()->setSubmissionReturn()` instead
     * @param null|ReturnedWork $submissionReturn
     * @return $this
     */
    public function setSubmissionReturn(?ReturnedWork $submissionReturn): self
    {
        trigger_error(
            'This method is deprecated; use "getGroupMark()->setSubmissionReturn()" instead',
            E_USER_DEPRECATED
        );
//        $this->submissionReturn = $submissionReturn;
        $this->groupMark->setSubmissionReturn($submissionReturn);
        return $this;
    }


    /**
     * @return null|Descriptor
     */
    public function getDescriptor(): ?Descriptor
    {
        return $this->descriptor;
    }


    /**
     * @param null|Descriptor $descriptor
     * @return static
     */
    public function setDescriptor(?Descriptor $descriptor): self
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
