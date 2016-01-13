<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric;


use InvalidArgumentException;
use Schoolbox\Assessment\Rubric\Competency;
use Scrub;
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
     * @param int  $id
     * @param bool $multiSelect
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
        // validation
        if (!$descriptor instanceof Descriptor) {
            throw new InvalidArgumentException("Invalid Descriptor given");
        }

        // ensure both the group and the level exist
        $this->putGroup($group);
        $this->putLevel($level);

        // ensure the criterion exists in the group
        $group->putCriterion($criterion);

        // ensure the descriptor exists on both the criterion and the level, cross-referencing the ID of the other
        $criterion->putDescriptor($descriptor, $level->getId());
        $level->putDescriptor($descriptor, $criterion->getId());

        // back-reference the level and crit on the descriptor, we need this for DB persistence.
        $descriptor->setPosition($level, $criterion);

        // back-reference the group onto the crit, we need this for DB persistence.
        $criterion->setPosition($group);

        return $this;
    }


    /**
     * @param Criteria\Group $group
     * @return self
     */
    public function putGroup($group)
    {
        if (!$group instanceof Criteria\Group) {
            throw new InvalidArgumentException("Invalid Group given");
        }

        $this->criteriaGroups->attach($group);
        return $this;
    }


    /**
     * @param Criteria\Group $group
     * @param Criteria\Criterion $criterion
     */
    public function putCriterion($group, $criterion)
    {
        $group->putCriterion($criterion);
        $this->putGroup($group);
    }


    /**
     * @param Competency\Level $level
     * @return self
     */
    public function putLevel($level)
    {
        if (!$level instanceof Competency\Level) {
            throw new InvalidArgumentException("Invalid Level given");
        }

        $this->levels->attach($level);
        return $this;
    }


    /**
     * @return SplObjectStorage|Criteria\Group[]
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
     * @return SplObjectStorage|Criteria\Group[]
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
     * @return SplObjectStorage|Criteria\Group[]
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
            $context['levels'][] = $level->toContext();
        }

        foreach ($this->criteriaGroups as $group) {
            $context['groups'][$group->getId()] = $group->toContext();
        }

        // little bit of data hanky panky: generate the descriptor grid.
        // (I apologise that this isn't as efficient as it should be - if you can think of how to improve it, by all means please do)
        foreach ($context['groups'] as $groupId => $group) {
            foreach ($group['criteria'] as $critId => $crit) {
                foreach ($context['levels'] as $levelSequence => $level) {
                    foreach ($level['descriptors'] as $crossRefCritId => $descriptor1) {
                        foreach ($crit['descriptors'] as $crossRefLevelId => $descriptor2) {
                            if ($crossRefCritId == $critId && $crossRefLevelId == $level['id']) {
                                $context['descriptors'][$critId][$levelSequence] = $descriptor1;
                            }
                        }
                    }
                }
            }
        }

        return $context;
    }


    /**
     * Return a CSV-compatible (ie. rectangular) array representation of the data structure this rubric contains
     *
     * @return array
     */
    public function toGrid()
    {
        // start by generating a rectangle which is (w+2 x 2h+1)
        $width  = $this->levels->count();
        $height = 0;

        foreach ($this->criteriaGroups as $group) {
            $height += $group->getAllCriteria()->count();
        }

        // rows contain columns
        $square = array_fill(0, (2 * $height) + 1, array_fill(0, $width + 2, ''));


        // first row is level names
        foreach ($this->levels as $i => $level) {
            $square[0][$i + 2] = $level->getName();
        }

        $currentDRow = 1;
        foreach ($this->criteriaGroups as $group) {
            $groupRendered = false;
            foreach ($group->getAllCriteria() as $criterion) {

                $square[$currentDRow][0] = $groupRendered ? '' : Scrub::csv($group->getName());
                $square[$currentDRow][1] = Scrub::csv($criterion->getName());

                $groupRendered = true;

                $currentVRow = $currentDRow + 1;

                foreach ($this->levels as $currentColumn => $level) {
                    foreach ($level->getAllDescriptors() as $levelD) {
                        foreach ($criterion->getAllDescriptors() as $critD) {
                            if ($levelD === $critD) {
                                $square[$currentDRow][$currentColumn + 2] = Scrub::csv($critD->getText());
                                $square[$currentVRow][$currentColumn + 2] = Scrub::csv($critD->getValue());
                            }
                        }
                    }
                }

                $currentDRow += 2;
            }
        }

        return $square;
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


    /**
     * @return float
     */
    public function getSum()
    {
        return $this->criteriaGroups->getSum();
    }


    /**
     * @return float
     */
    public function getPercentage()
    {
        if ($total = $this->getTotal()) {
            return $this->getSum() / $this->getTotal();
        }
        return 0;
    }

}
