<?php
/**
 * Model for rubric criteria.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;

class Criterion implements ISoftDeletable
{
    use Id;
    use DeletedAtDate;

    const PARENT_TYPE_CATEGORY   = 'category';
    const PARENT_TYPE_SUBMISSION = 'submission';
    const PARENT_TYPE_CURRICULUM = 'curriculum';

    /**
     * @var string
     */
    private $name;


    /**
     * @var Group
     */
    private $group;


    /**
     * @deprecated use `$this->getGroup()->setName()` instead
     * @param  string $grouping
     * @return $this
     */
    public function setGrouping($grouping)
    {
        trigger_error('This method is deprecated; use "getGroup->setName" instead', E_USER_DEPRECATED);
//        $this->grouping = $grouping;
        $this->group->setName($grouping);
        return $this;
    }

    /**
     * @return string
     */
    public function getGrouping()
    {
        trigger_error('This method is deprecated; use "getGroup->getName" instead', E_USER_DEPRECATED);
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
     * @param  int $parent_id
     * @return $this
     */
    public function setParentId($parent_id)
    {
        trigger_error('This method is deprecated; use "getGroup->setParentId" instead', E_USER_DEPRECATED);
//        $this->parent_id = $parent_id;
        $this->group->setParentId($parent_id);
        return $this;
    }

    /**
     * @return int
     */
    public function getParentId()
    {
        trigger_error('This method is deprecated; use "getGroup->getParentId" instead', E_USER_DEPRECATED);
//        return $this->parent_id;
        return $this->group->getParentId();
    }

    /**
     * @param  string $parent_type
     * @return $this
     */
    public function setParentType($parent_type)
    {
        trigger_error('This method is deprecated; use "getGroup->setParentType" instead', E_USER_DEPRECATED);
//        $this->parent_type = $parent_type;
        $this->group->setParentType($parent_type);
        return $this;
    }

    /**
     * @return string
     */
    public function getParentType()
    {
        trigger_error('This method is deprecated; use "getGroup->getParentType" instead', E_USER_DEPRECATED);
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
