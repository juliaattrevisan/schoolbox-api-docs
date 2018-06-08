<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * Model for rubric groups.
 */
class Group implements ISoftDeletable
{
    use Id;
    use DeletedAtDate;

    public const PARENT_TYPE_CATEGORY = 'category';
    public const PARENT_TYPE_SUBMISSION = 'submission';
    public const PARENT_TYPE_CURRICULUM = 'curriculum';

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
    private $parentId;

    /**
     * @var string
     */
    private $parentType = self::PARENT_TYPE_CATEGORY;

    /**
     * @return string
     */
    public function getGrouping(): string
    {
        return $this->grouping;
    }

    /**
     * @param string $grouping
     * @return static
     */
    public function setGrouping(string $grouping)
    {
        $this->grouping = $grouping;
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
    public function setName(string $name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return int
     */
    public function getParentId(): int
    {
        return $this->parentId;
    }

    /**
     * @param int $parentId
     * @return static
     */
    public function setParentId(int $parentId)
    {
        $this->parentId = $parentId;
        return $this;
    }

    /**
     * @return string
     */
    public function getParentType(): string
    {
        return $this->parentType;
    }

    /**
     * @param string $parentType
     * @return static
     */
    public function setParentType(string $parentType)
    {
        $this->parentType = $parentType;
        return $this;
    }
}
