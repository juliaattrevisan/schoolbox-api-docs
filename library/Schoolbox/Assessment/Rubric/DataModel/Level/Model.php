<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel\Level;


use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Rubric\DataModel\Descriptor\Model as DescriptorModel;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;

class Model implements ISoftDeletable
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
     * @var DescriptorModel[]|GenericCollection
     */
    private $descriptors;


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
     * @return DescriptorModel[]|GenericCollection
     */
    public function getDescriptors()
    {
        return $this->descriptors;
    }

    /**
     * @param DescriptorModel[]|GenericCollection $descriptors
     * @return static
     */
    public function setDescriptors(GenericCollection $descriptors)
    {
        $descriptors->assertCollectionOf(DescriptorModel::class);
        $this->descriptors = $descriptors;
        return $this;
    }
}
