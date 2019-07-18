<?php
/**
 * Model for rubric criteria.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel;

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
     * @var Group
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
     * @return Group
     */
    public function getGroup(): Group
    {
        return $this->group;
    }

    /**
     * @param Group $group
     * @return static
     */
    public function setGroup(Group $group)
    {
        $this->group = $group;
        return $this;
    }
}
