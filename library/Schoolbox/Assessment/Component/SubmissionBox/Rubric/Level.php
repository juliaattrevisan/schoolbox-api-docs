<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;


use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;

class Level implements ISoftDeletable
{
    use Id;
    use DeletedAtDate;

    /** @var SubmissionBox */
    private $submissionBox;

    /** @var string */
    private $name;

    /**
     * 0-indexed
     *
     * @var int
     */
    private $sequence;

    /**
     * @var Descriptor[]|GenericCollection
     */
    private $descriptors;


    public function __construct()
    {
        $this->descriptors = DescriptorMapper::getInstance()->createCollection();
    }

    /**
     * @return SubmissionBox
     */
    public function getSubmissionBox()
    {
        return $this->submissionBox;
    }


    /**
     * @param SubmissionBox $submissionBox
     * @return static
     */
    public function setSubmissionBox(SubmissionBox $submissionBox)
    {
        $this->submissionBox = $submissionBox;
        return $this;
    }


    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * @param string $name
     * @return static
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }


    /**
     * @return int
     */
    public function getSequence()
    {
        return $this->sequence;
    }


    /**
     * @param int $sequence
     * @return static
     */
    public function setSequence($sequence)
    {
        $this->sequence = $sequence;
        return $this;
    }

    /**
     * @return Descriptor[]|GenericCollection
     */
    public function getDescriptors()
    {
        return $this->descriptors;
    }

    /**
     * @param Descriptor[]|GenericCollection $descriptors
     * @return static
     */
    public function setDescriptors(GenericCollection $descriptors)
    {
        $this->descriptors->replaceWith($descriptors);
        return $this;
    }
}
