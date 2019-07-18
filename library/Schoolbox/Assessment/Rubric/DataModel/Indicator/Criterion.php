<?php
/**
 * Model for rubric criteria.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel\Indicator;

use Schoolbox\Assessment\Rubric\DataModel\Capability\Model;
use Schoolbox\Curriculum\Node\Model as NodeModel;
use Schoolbox\Curriculum\Node\Traits\HasCurriculumNodes;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;

class Criterion implements ISoftDeletable
{
    use Id;
    use DeletedAtDate;
    use HasCurriculumNodes;


    /**
     * @var string
     */
    private $name;


    /**
     * @var Model
     */
    private $group;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->curriculumNodes = new GenericCollection(NodeModel::class);
    }

    /**
     * @param  string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
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
    public function setGroup(Model $group)
    {
        $this->group = $group;
        return $this;
    }
}
