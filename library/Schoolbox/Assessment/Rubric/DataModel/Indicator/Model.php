<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel\Indicator;

use Schoolbox\Assessment\Rubric\DataModel\Capability\Model as CapabilityModel;
use Schoolbox\Curriculum\Node\Traits\HasCurriculumNodes;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * Model for rubric indicators.
 */
class Model implements ISoftDeletable
{
    use Id;
    use DeletedAtDate;
    use HasCurriculumNodes;


    /**
     * @var string
     */
    private string $name;


    /**
     * @var CapabilityModel
     */
    private CapabilityModel $capability;


    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }


    /**
     * @param  string $name
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }


    /**
     * @return CapabilityModel
     */
    public function getCapability(): CapabilityModel
    {
        return $this->capability;
    }


    /**
     * @param CapabilityModel $capability
     * @return $this
     */
    public function setCapability(CapabilityModel $capability): self
    {
        $this->capability = $capability;
        return $this;
    }
}
