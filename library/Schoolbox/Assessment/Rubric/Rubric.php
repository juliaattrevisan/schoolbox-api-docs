<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric;


use Schoolbox\Assessment\Rubric\Competency;
use Schoolbox\Assessment\Rubric\Criteria\Group;
use SplObjectStorage;

class Rubric implements Twiggable
{

    /** @var int */
    protected $id;

    /** @var bool */
    protected $multiSelect;

    /** @var Criteria\Group[]|CalculableAggregator */
    protected $criteriaGroups;

    /** @var Competency\Level[]|CalculableAggregator */
    protected $levels;


    /**
     */
    public function __construct($id = null, $multiSelect = false)
    {
        $this->criteriaGroups = new CalculableAggregator();
        $this->levels = new CalculableAggregator();
        $this->setId($id ?: rand(1,5038576));
        $this->setMultiSelect($multiSelect);
    }


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @param int $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }


    /**
     * @deprecated used by Mapper only
     * @return bool
     */
    public function getMultiSelect()
    {
        return $this->isMultiSelect();
    }


    /**
     * @return boolean
     */
    public function isMultiSelect()
    {
        return $this->multiSelect;
    }


    /**
     * @param boolean $multiSelect
     * @return static
     */
    public function setMultiSelect($multiSelect)
    {
        $this->multiSelect = $multiSelect;
        return $this;
    }


    /**
     * Put the given criterion, level and descriptor into the rubric
     *
     * This is the only mutation method available for the rubric: it's important that empty criteria
     * and levels are not allowed to exist, and that the descriptor is injected at the correct intersection.
     *
     * @param Criteria\Group     $group
     * @param Criteria\Criterion $criterion
     * @param Competency\Level   $level
     * @param Descriptor         $descriptor
     * @return self
     */
    public function putDescriptor($group, $criterion, $level, $descriptor)
    {
        // ensure both the group and the level exist
        $this->putGroup($group);
        $this->putLevel($level);

        // ensure the criterion exists in the group
        $group->putCriterion($criterion);

        // ensure the descriptor exists on both the criterion and the level, cross-referencing the ID of the other
        $criterion->putDescriptor($descriptor, $level->getId());
        $level->putDescriptor($descriptor, $criterion->getId());

        return $this;
    }


    /**
     * @param Criteria\Group $group
     * @return self
     */
    public function putGroup($group)
    {
        $this->criteriaGroups->attach($group);
        return $this;
    }


    /**
     * @param Competency\Level $level
     * @return self
     */
    public function putLevel($level)
    {
        $this->levels->attach($level);
        return $this;
    }


    /**
     * @return SplObjectStorage|Group[]
     */
    public function getSumPerGroup()
    {
        $os = new SplObjectStorage();

        foreach ($this->criteriaGroups as $group)
        {
            $os->attach($group, $group->getSum());
        }

        return $os;
    }


    /**
     * @return SplObjectStorage|Group[]
     */
    public function getTotalPerGroup()
    {
        $os = new SplObjectStorage();

        foreach ($this->criteriaGroups as $group)
        {
            $os->attach($group, $group->getTotal());
        }

        return $os;
    }


    /**
     * Return the percentages of this rubric, grouped by group
     *
     * @return SplObjectStorage|Group[]
     */
    public function getPercentagePerGroup()
    {
        $os = new SplObjectStorage();

        foreach ($this->criteriaGroups as $group)
        {
            $os->attach($group, $group->getPercentage());
        }

        return $os;
    }


    /**
     * Return the Twig representation of the data structure this rubric contains
     *
     * @return array
     */
    public function toContext()
    {
        $context = [
            'id'          => $this->getId(),
            'multiSelect' => $this->isMultiSelect(),
            'sum'         => $this->criteriaGroups->getSum(),
            'total'       => $this->getTotal(),
            'percentage'  => $this->levels->getPercentage(),
            'levels'      => [],
            'groups'      => [],
        ];

        foreach ($this->levels as $level) {
            $context['levels'][$level->getId()] = $level->toContext();
        }

        foreach ($this->criteriaGroups as $group) {
            $context['groups'][$group->getId()] = $group->toContext();
        }

        // little bit of data hanky panky: generate the descriptor grid.
        // (I apologise that this isn't as efficient as it should be - if you can think of how to improve it, by all means please do)
        foreach ($context['groups'] as $groupId => $group) {
            foreach ($group['criteria'] as $critId => $crit) {
                foreach ($context['levels'] as $levelId => $level) {
                    foreach ($level['descriptors'] as $crossRefCritId => $descriptor1) {
                        foreach ($crit['descriptors'] as $crossRefLevelId => $descriptor2) {
                            if ($crossRefCritId == $critId && $crossRefLevelId == $levelId) {
                                $context['descriptors'][$critId][$levelId] = $descriptor1;
                            }
                        }
                    }
                }
            }
        }

        return $context;
    }


    /**
     * Returns the total of the values of descriptors contained herein, irrespective of selection
     *
     * @return float
     */
    public function getTotal()
    {
        $total = 0;

        foreach ($this->criteriaGroups as $calc) {
            $total += $calc->getTotal();
        }

        return $total;

    }

}
