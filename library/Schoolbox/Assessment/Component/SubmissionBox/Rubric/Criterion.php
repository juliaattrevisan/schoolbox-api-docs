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
use Alaress_Date_Format;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;

class Criterion implements ISoftDeletable
{
    use Id;
    use DeletedAtDate;

    const PARENT_TYPE_CATEGORY = 'category';
    const PARENT_TYPE_SUBMISSION = 'submission';
    const PARENT_TYPE_CURRICULUM = 'curriculum';

    /**
     * @var string
     */
    private $grouping;

    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $parent_id;

    /**
     * @var string
     */
    private $parent_type = self::PARENT_TYPE_CATEGORY;


    /**
     * @param  string $grouping
     * @return $this
     */
    public function setGrouping($grouping)
    {
        $this->grouping = $grouping;
        return $this;
    }

    /**
     * @return string
     */
    public function getGrouping()
    {
        return $this->grouping;
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
        $this->parent_id = $parent_id;
        return $this;
    }

    /**
     * @return int
     */
    public function getParentId()
    {
        return $this->parent_id;
    }

    /**
     * @param  string $parent_type
     * @return $this
     */
    public function setParentType($parent_type)
    {
        $this->parent_type = $parent_type;
        return $this;
    }

    /**
     * @return string
     */
    public function getParentType()
    {
        return $this->parent_type;
    }
}
