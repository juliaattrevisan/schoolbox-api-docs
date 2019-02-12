<?php
/**
 * Model for rubric criteria.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

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
     * @deprecated use `Group::PARENT_TYPE_CATEGORY` instead
     */
    public const PARENT_TYPE_CATEGORY   = 'category';

    /**
     * @deprecated use `Group::PARENT_TYPE_SUBMISSION` instead
     */
    public const PARENT_TYPE_SUBMISSION = 'submission';

    /**
     * @deprecated use `Group::PARENT_TYPE_CURRICULUM` instead
     */
    public const PARENT_TYPE_CURRICULUM = 'curriculum';

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
     * Retrieves a normalized form of this object's name, for comparison with
     * other objects.
     * * Leading and trailing spaces are stripped
     * * Multiple consecutive spaces are converted to single spaces
     *
     * @return string
     */
    public function retrieveNormalizedName(): string
    {
        return preg_replace('`\s+`', ' ', trim($this->name));
    }

    /**
     * @deprecated use `$this->getGroup()->setName()` instead
     * @param  string $grouping
     * @return $this
     */
    public function setGrouping($grouping)
    {
        trigger_error('This method is deprecated; use "getGroup()->setName()" instead', E_USER_DEPRECATED);
//        $this->grouping = $grouping;
        $this->group->setName($grouping);
        return $this;
    }

    /**
     * @deprecated use `$this->getGroup()->getName()` instead
     * @return string
     */
    public function getGrouping()
    {
        trigger_error('This method is deprecated; use "getGroup()->getName()" instead', E_USER_DEPRECATED);
//        return $this->grouping;
        return $this->group->getName();
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
     * @deprecated use `$this->getGroup()->setParentId()` instead
     * @param  int $parent_id
     * @return $this
     */
    public function setParentId($parent_id)
    {
        trigger_error('This method is deprecated; use "getGroup()->setParentId()" instead', E_USER_DEPRECATED);
//        $this->parent_id = $parent_id;
        $this->group->setParentId($parent_id);
        return $this;
    }

    /**
     * @deprecated use `$this->getGroup()->getParentId()` instead
     * @return int
     */
    public function getParentId()
    {
        trigger_error('This method is deprecated; use "getGroup()->getParentId()" instead', E_USER_DEPRECATED);
//        return $this->parent_id;
        return $this->group->getParentId();
    }

    /**
     * @deprecated use `$this->getGroup()->setParentType()` instead
     * @param  string $parent_type
     * @return $this
     */
    public function setParentType($parent_type)
    {
        trigger_error('This method is deprecated; use "getGroup()->setParentType()" instead', E_USER_DEPRECATED);
//        $this->parent_type = $parent_type;
        $this->group->setParentType($parent_type);
        return $this;
    }

    /**
     * @deprecated use `$this->getGroup()->getParentType()` instead
     * @return string
     */
    public function getParentType()
    {
        trigger_error('This method is deprecated; use "getGroup()->getParentType()" instead', E_USER_DEPRECATED);
//        return $this->parent_type;
        return $this->group->getParentType();
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
