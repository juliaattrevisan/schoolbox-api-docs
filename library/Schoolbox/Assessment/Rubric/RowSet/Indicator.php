<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\RowSet;

use Schoolbox\Curriculum\Node\Traits\HasCurriculumNodes;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;

/**
 * An indicator within a rubric rowset.
 */
class Indicator
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
     * Assessments which are using this indicator.
     *
     * @var GenericCollection|IAssessable[]
     */
    private $usage;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->usage = new GenericCollection(IAssessable::class);
    }


    /**
     * Is this indicator in use on any existing assessment?
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
        return \count($this->usage) > 0;
    }


    /**
     * Is this indicator in use on any existing assessment, excluding one
     * particular assessment?
     *
     * @param IAssessable $exclusion Assessment to exclude from usage check
     * @return bool
     */
    public function isInUseExcluding(IAssessable $exclusion): bool
    {
        foreach ($this->usage as $assessment) {
            // Skip the exclusion if it matches
            if ((int)$exclusion->getId() === (int)$assessment->getId()) {
                continue;
            }
            return true;
        }
        return false;
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
