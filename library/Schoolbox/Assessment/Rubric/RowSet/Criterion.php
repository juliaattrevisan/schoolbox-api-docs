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
 * A criterion within a rubric rowset.
 */
class Criterion
{
    use Id;
    use HasCurriculumNodes;


    /**
     * @var string
     */
    private $name;


    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }


    /**
     * Assessments which are using this criterion.
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
     * Is this criterion in use on any existing assessment?
     *
     * @return bool
     */
    public function isInUse(): bool
    {
        return \count($this->usage) > 0;
    }


    /**
     * Is this criterion in use on any existing assessment, excluding one
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
