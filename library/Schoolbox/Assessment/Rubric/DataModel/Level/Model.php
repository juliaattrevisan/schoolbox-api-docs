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


    /**
     * @var null|\Schoolbox\Assessment\Rubric\Instance\Model
     */
    private ?\Schoolbox\Assessment\Rubric\Instance\Model $rubricInstance;


    /**
     * @var string
     */
    private string $name;


    /**
     * 0-indexed
     *
     * (I wonder why this is zero indexed but capabilityUsages are 1-indexed...)
     *
     * @var int
     */
    private int $sequence;


    /**
     * @var GenericCollection<DescriptorModel>
     */
    private GenericCollection $descriptors;


    /**
     * @return null|\Schoolbox\Assessment\Rubric\Instance\Model
     */
    public function getRubricInstance(): ?\Schoolbox\Assessment\Rubric\Instance\Model
    {
        return $this->rubricInstance;
    }


    /**
     * @param null|\Schoolbox\Assessment\Rubric\Instance\Model $rubricInstance
     * @return $this
     */
    public function setRubricInstance(?\Schoolbox\Assessment\Rubric\Instance\Model $rubricInstance): self
    {
        $this->rubricInstance = $rubricInstance;
        return $this;
    }


    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }


    /**
     * @param string $name
     * @return static
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }


    /**
     * @return int
     */
    public function getSequence(): int
    {
        return $this->sequence;
    }


    /**
     * @param int $sequence
     * @return static
     */
    public function setSequence(int $sequence): self
    {
        $this->sequence = $sequence;
        return $this;
    }


    /**
     * @return GenericCollection<DescriptorModel>
     */
    public function getDescriptors(): GenericCollection
    {
        return $this->descriptors;
    }


    /**
     * @param GenericCollection<DescriptorModel> $descriptors
     * @return static
     */
    public function setDescriptors(GenericCollection $descriptors): self
    {
        $descriptors->assertCollectionOf(DescriptorModel::class);
        $this->descriptors = $descriptors;

        return $this;
    }
}
