<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\RowSet;

use Schoolbox\Assessment\Rubric\Util;
use Schoolbox\Curriculum\Node\Traits\HasCurriculumNodes;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;

/**
 * A capability within a rubric rowset.
 */
class Capability
{
    use HasCurriculumNodes;

    /**
     * @var int|null
     */
    private $id;


    /**
     * @var string
     */
    private $name;


    /**
     * Does this capability belong to the course level of its assessment
     * context?
     *
     * For rowsets which are part of a unit: it's possible for a capability to
     * come from the unit's course, but still be part of the unit's rowset.
     *
     * @var bool
     */
    private $isCourseLevel = false;


    /**
     * @var GenericCollection|Indicator[]
     */
    private $indicators;


    /**
     * Assessments which are using this capability.
     *
     * @var GenericCollection|IAssessable[]
     */
    private $usage;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->indicators = new GenericCollection(Indicator::class);
        $this->usage      = new GenericCollection(IAssessable::class);
    }


    /**
     * Retrieves an indicator by its ID.
     *
     * @param int $indicatorId
     * @return null|Indicator
     */
    public function retrieveIndicator(int $indicatorId): ?Indicator
    {
        return $this->indicators->getBy('id', $indicatorId);
    }


    /**
     * Retrieves an indicator by its name.
     *
     * @param string $name
     * @return Indicator|null
     */
    public function retrieveIndicatorByName(string $name): ?Indicator
    {
        $normalizedName = Util::normalizeName($name);
        foreach ($this->indicators as $indicator) {
            if (Util::normalizeName($indicator->getName()) === $normalizedName) {
                return $indicator;
            }
        }
        return null;
    }


    /**
     * Retrieve the ID of all indicators in this capability.
     *
     * @return array|int[]
     */
    public function retrieveIndicatorIds(): array
    {
        $result = array_unique(array_filter($this->indicators->getPropertyValues('id')));
        sort($result);
        return $result;
    }


    /**
     * Are any of the indicators in this capability in use?
     *
     * @param null|IAssessable $exclusion If set, assessment to exclude from
     *                                    usage check
     * @return bool
     */
    public function isInUse(?IAssessable $exclusion = null): bool
    {
        if ($exclusion) {
            return $this->isInUseExcluding($exclusion);
        }
        foreach ($this->indicators as $indicator) {
            if ($indicator->isInUse()) {
                return true;
            }
        }
        return false;
    }


    /**
     * Is this capability in use on any existing assessment, excluding one
     * particular assessment?
     *
     * @param IAssessable $exclusion Assessment to exclude from usage check
     * @return bool
     */
    public function isInUseExcluding(IAssessable $exclusion): bool
    {
        foreach ($this->indicators as $indicator) {
            if ($indicator->isInUseExcluding($exclusion)) {
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
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }


    /**
     * @param int|null $id
     * @return static
     */
    public function setId(?int $id): self
    {
        $this->id = $id;
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
     * @return GenericCollection|Indicator[]
     */
    public function getIndicators(): GenericCollection
    {
        return $this->indicators;
    }


    /**
     * @param GenericCollection|Indicator[] $indicators
     * @return static
     */
    public function setIndicators(GenericCollection $indicators): self
    {
        $this->indicators = $indicators;
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
