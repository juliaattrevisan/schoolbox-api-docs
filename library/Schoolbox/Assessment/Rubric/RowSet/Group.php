<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\RowSet;

use Schoolbox\Curriculum\Node\Traits\HasCurriculumNodes;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * A group within a rubric rowset.
 */
class Group
{
    use Id;
    use HasCurriculumNodes;


    /**
     * @var string
     */
    private $name;


    /**
     * Does this group belong to the course level of its assessment context?
     *
     * For rowsets which are part of a unit: it's possible for a group to come
     * from the unit's course, but still be part of the unit's rowset.
     *
     * @var bool
     */
    private $isCourseLevel = false;


    /**
     * @var GenericCollection|Criterion[]
     */
    private $criteria;


    /**
     * Assessments which are using this group.
     *
     * @var GenericCollection|IAssessable[]
     */
    private $usage;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->criteria = new GenericCollection(Criterion::class);
        $this->usage    = new GenericCollection(IAssessable::class);
    }


    /**
     * Retrieves a criterion by its ID.
     *
     * @param int $criterionId
     * @return null|Criterion
     */
    public function retrieveCriterion(int $criterionId): ?Criterion
    {
        return $this->criteria->getBy('id', $criterionId);
    }


    /**
     * Retrieve the ID of all criteria in this group.
     *
     * @return array|int[]
     */
    public function retrieveCriterionIds(): array
    {
        $result = array_unique(array_filter($this->criteria->getPropertyValues('id')));
        sort($result);
        return $result;
    }


    /**
     * Are any of the criteria in this group in use?
     *
     * @return bool
     */
    public function isInUse(): bool
    {
        foreach ($this->criteria as $criterion) {
            if ($criterion->isInUse()) {
                return true;
            }
        }
        return false;
    }


    /**
     * Is this group in use on any existing assessment, excluding one
     * particular assessment?
     *
     * @param IAssessable $exclusion Assessment to exclude from usage check
     * @return bool
     */
    public function isInUseExcluding(IAssessable $exclusion): bool
    {
        foreach ($this->criteria as $criterion) {
            if ($criterion->isInUseExcluding($exclusion)) {
                return true;
            }
        }
        return false;
    }


    /**
     * @return bool
     */
    public function isCourseLevel(): bool
    {
        return $this->isCourseLevel;
    }


    /**
     * @param bool $isCourseLevel
     * @return static
     */
    public function setIsCourseLevel(bool $isCourseLevel): self
    {
        $this->isCourseLevel = $isCourseLevel;
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
     * @return GenericCollection|Criterion[]
     */
    public function getCriteria(): GenericCollection
    {
        return $this->criteria;
    }


    /**
     * @param GenericCollection|Criterion[] $criteria
     * @return static
     */
    public function setCriteria(GenericCollection $criteria): self
    {
        $this->criteria = $criteria;
        return $this;
    }


    /**
     * @return GenericCollection|IAssessable[]
     */
    public function getUsage(): GenericCollection
    {
        return $this->usage;
    }


    /**
     * @param GenericCollection|IAssessable[] $usage
     * @return static
     */
    public function setUsage(GenericCollection $usage): self
    {
        $this->usage = $usage;
        return $this;
    }
}
