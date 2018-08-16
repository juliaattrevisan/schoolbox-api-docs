<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\RowSet;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\AssessmentContext;

/**
 * A rubric row set.
 *
 * This is a representation of the groups (and their constituent criteria)
 * which are available to an assessment context.
 *
 * It contains:
 * * an assessment context (in practise, always a folder)
 * * a set of rubric groups
 */
class RowSet
{
    /**
     * The context within which this row set lives.
     *
     * In practise, this is always a folder.
     *
     * @var AssessmentContext
     */
    private $context;


    /**
     * The groups which comprise this row set.
     *
     * @var Group[]|GenericCollection
     */
    private $groups;


    /**
     * Constructor
     *
     * @param AssessmentContext $context
     */
    public function __construct(AssessmentContext $context)
    {
        $this->context = $context;
        $this->groups  = new GenericCollection(Group::class);
    }


    /**
     * @return AssessmentContext
     */
    public function getContext(): AssessmentContext
    {
        return $this->context;
    }


    /**
     * @return Group[]|GenericCollection
     */
    public function getGroups(): GenericCollection
    {
        return $this->groups;
    }


    /**
     * @param Group[]|GenericCollection $groups
     * @return static
     */
    public function setGroups(GenericCollection $groups): self
    {
        $this->groups = $groups;
        return $this;
    }
}
