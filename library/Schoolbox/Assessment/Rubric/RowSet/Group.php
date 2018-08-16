<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\RowSet;

use Schoolbox\Curriculum\Node\Traits\HasCurriculumNodes;
use Schoolbox\GenericCollection;
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
     * @var GenericCollection|Criterion[]
     */
    private $criteria;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->criteria = new GenericCollection(Criterion::class);
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
}
