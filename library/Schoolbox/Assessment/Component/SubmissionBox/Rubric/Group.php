<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Schoolbox\Curriculum\Node\Model as NodeModel;
use Schoolbox\Curriculum\Node\Traits\HasCurriculumNodes;
use Schoolbox\GenericCollection;
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
    use HasCurriculumNodes;

    /**
     * Constant for the 'category' parent type; this indicates that
     * `$this->parentId` references a folder ID.
     *
     * All known rubric groups have this parent type.
     *
     * @var string
     */
    public const PARENT_TYPE_CATEGORY = 'category';

    /**
     * Constant for the 'submission' parent type; this indicates that
     * `$this->parentId` references a submission box's ID.
     *
     * No known rubric groups have or have ever had this parent type; it may
     * have been added for future use for rubric criteria unique to a due work,
     * but this was never implemented.
     *
     * @deprecated this has never been used
     * @var string
     */
    public const PARENT_TYPE_SUBMISSION = 'submission';

    /**
     * Constant for the 'curriculum' parent type; this indicates that
     * `$this->parentId` references a curriculum ID.
     *
     * No known rubric groups have or have ever had this parent type; it may
     * have been added for future use for rubric criteria unique to a
     * curriculum, but this was never implemented.
     *
     * @deprecated this has never been used
     * @var string
     */
    public const PARENT_TYPE_CURRICULUM = 'curriculum';

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
