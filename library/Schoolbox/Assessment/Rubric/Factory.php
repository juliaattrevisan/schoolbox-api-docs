<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric;


use Schoolbox\Assessment\Rubric\Competency\Level;
use Schoolbox\Assessment\Rubric\Criteria\Criterion;
use Schoolbox\Assessment\Rubric\Criteria\Group;

class Factory
{

    /** @var int */
    protected $rubricId = null;

    /** @var bool */
    protected $multiSelect = false;


    /**
     * @param int $rubricId
     * @return static
     */
    public function setRubricId($rubricId)
    {
        $this->rubricId = $rubricId;
        return $this;
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
     * Build an empty rubric (which must contain 2 levels and 'ungrouped')
     *
     * @return Rubric
     */
    public function buildEmpty()
    {
        return $this->buildFromArrayMap([ 1 => '', 2 => '' ], [ '' => [] ]);
    }

    /**
     * @deprecated              you should really use the model implementation
     * @param array $levels     a flat list of [ id => name ]
     * @param array $criteria   a data structure of [ groupName => [ critName => [ [ descriptorText, descriptorValue ] ] ] ]
     * @return Rubric
     */
    public function buildFromArrayMap(array $levels, array $criteria)
    {

        $groupIndex = $critIndex = $dIndex = -1;
        $groupList = $critList = $d = $levelList = [];

        foreach ($criteria as $groupName => $groupData) {

            ++$groupIndex;

            $groupList[$groupIndex] = new Group($groupIndex, $groupName);

            if (!is_array($groupData)) {
                continue;
            }

            foreach ($groupData as $critName => $critData) {

                ++$critIndex;

                $critList[$groupIndex][$critIndex] = new Criterion($critIndex, $critName);

                if (!is_array($critData)) {
                    continue;
                }

                $dIndex = -1;

                foreach ($critData as $descriptor) {

                    ++$dIndex;

                    $d[$groupIndex][$critIndex][$dIndex] = new Descriptor($descriptor[0], $descriptor[1]);

                }

            }

        }

        foreach ($levels as $id => $name) {
            $levelList[] = new Level($id, $name);
        }


        $rubric = new Rubric($this->rubricId, $this->multiSelect);

        // ensure we put the levels independently of the criteria
        foreach ($levelList as $levelIndex => $level) {
            $rubric->putLevel($level);
        }

            // parse out a data structure
        foreach ($groupList as $groupIndex => $group) {

            $rubric->putGroup($group);

            if (!is_array($critList[$groupIndex])) {
                continue;
            }

            foreach ($critList[$groupIndex] as $critIndex => $crit) {

                /** @var Group $group */
                $group->putCriterion($crit);

                if (!is_array($levelList)) {
                    continue;
                }

                foreach ($levelList as $levelIndex => $level) {

                    if (isset($d[$groupIndex][$critIndex][$levelIndex])) {
                        $rubric->putDescriptor($group, $crit, $level, $d[$groupIndex][$critIndex][$levelIndex]);
                    }

                }

            }

        }

        return $rubric;
    }


}
