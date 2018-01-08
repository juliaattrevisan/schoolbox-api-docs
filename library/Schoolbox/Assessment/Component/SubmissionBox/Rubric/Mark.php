<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Mapper\Traits\Model\Id;


/**
 * Class Mark
 *
 * Semantically, this class should really be named Selection or similar, but for the sake of matching the db table, mark it is...
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\Rubric
 */
class Mark
{
    use Id;

    /** @var null|ReturnedWork */
    private $submissionReturn;

    /** @var Descriptor */
    private $descriptor;

    /** @var bool */
    private $valid;


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
    public function setSubmissionReturn(?ReturnedWork $submissionReturn)
    {
        $this->submissionReturn = $submissionReturn;
        return $this;
    }

    /**
     * @return Descriptor
     */
    public function getDescriptor()
    {
        return $this->descriptor;
    }

    /**
     * @param Descriptor $descriptor
     * @return static
     */
    public function setDescriptor(Descriptor $descriptor)
    {
        $this->descriptor = $descriptor;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isValid()
    {
        return $this->valid;
    }

    /**
     * @deprecated Mapper method only
     * @return bool
     */
    public function getValid()
    {
        return $this->isValid();
    }

    /**
     * @param boolean $valid
     * @return static
     */
    public function setValid($valid)
    {
        $this->valid = $valid;
        return $this;
    }
}
