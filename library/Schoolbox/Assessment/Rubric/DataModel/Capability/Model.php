<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel\Capability;

use Schoolbox\Assessment\Rubric\DataModel\Indicator\Model as IndicatorModel;
use Schoolbox\Curriculum\Node\Traits\HasCurriculumNodes;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use TypeError;

/**
 * Model for rubric capabilities.
 */
class Model implements ISoftDeletable
{
    use Id;
    use DeletedAtDate;
    use HasCurriculumNodes;

    /**
     * Constant for the 'category' parent type; this indicates that
     * `$this->parentId` references a folder ID.
     *
     * All known rubric capabilities have this parent type.
     */
    public const string PARENT_TYPE_CATEGORY = 'category';

//    /**
//     * Constant for the 'submission' parent type; this indicates that
//     * `$this->parentId` references a submission box's ID.
//     *
//     * No known rubric capabilities have or have ever had this parent type; it
//     * may have been added for future use for rubric indicators unique to a due
//     * work, but this was never implemented.
//     *
//     * @deprecated this has never been used
//     */
//    public const string PARENT_TYPE_SUBMISSION = 'submission';
//
//    /**
//     * Constant for the 'curriculum' parent type; this indicates that
//     * `$this->parentId` references a curriculum ID.
//     *
//     * No known rubric capabilities have or have ever had this parent type; it
//     * may have been added for future use for rubric indicators unique to a
//     * curriculum, but this was never implemented.
//     *
//     * @deprecated this has never been used
//     */
//    public const string PARENT_TYPE_CURRICULUM = 'curriculum';


    /**
     * @var string
     */
    private string $parentType = self::PARENT_TYPE_CATEGORY;


    /**
     * @var string
     */
    private string $name;


    /**
     * @var int
     */
    private int $parentId;


    /**
     * @var null|GenericCollection<IndicatorModel>
     */
    private ?GenericCollection $indicators = null;


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
    public function getParentId(): int
    {
        return $this->parentId;
    }


    /**
     * @param int $parentId
     * @return static
     */
    public function setParentId(int $parentId): self
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
    public function setParentType(string $parentType): self
    {
        $this->parentType = $parentType;
        return $this;
    }


    /**
     * @return null|GenericCollection<IndicatorModel>
     */
    public function getIndicators(): ?GenericCollection
    {
        return $this->indicators;
    }


    /**
     * @param GenericCollection<IndicatorModel> $indicators
     * @return $this
     * @throws TypeError
     */
    public function setIndicators(GenericCollection $indicators): self
    {
        $indicators->assertCollectionOf(IndicatorModel::class);
        $this->indicators = $indicators;
        return $this;
    }
}
