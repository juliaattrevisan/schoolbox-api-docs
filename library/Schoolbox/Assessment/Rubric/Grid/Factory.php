<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

use Schoolbox\Assessment\Rubric\RowSet\Criterion;
use Schoolbox\Assessment\Rubric\RowSet\Group;
use Schoolbox\Learning\Assessment\IAssessable;

/**
 * Class Factory
 */
class Factory
{
    /**
     * Port of `\Schoolbox\Assessment\Rubric\Factory::buildFromPostData`,
     * but builds a `Grid` object instead.
     *
     * @param Grid  $grid     A grid (which already contains an assessment and rowset)
     * @param array $postData
     * @return Grid
     */
    public function buildFromPostData(Grid $grid, array $postData): Grid
    {
        // we're only going to take the first one... this could be converted to foreach easily enough
        $rubricData = reset($postData);
//        $rubricId   = key($postData);

        $levels      = $rubricData['level']      ?: [];
        $groups      = $rubricData['group']      ?: [];
        $criteria    = $rubricData['criterion']  ?: [];
        $descriptors = $rubricData['descriptor'] ?: [];

//        $rubric = $this->spawn($rubricId);

        /** @var Level[] $levelCache */
        $levelCache = [];

        // cache levels and ensure all exist in the rubric
        foreach ($levels as $levelSequence => $level) {
            $levelName = $level['name'];
            $levelId   = $level['id'] ?? null;

            $levelCache[$levelSequence] = (new Level())
                ->setId($levelId)
                ->setName($levelName);

//            $rubric->putLevel($levelCache[$levelSequence]);
            $grid->getLevels()->add($levelCache[$levelSequence]);
        }


        // Must assume that all groups in formdata exist
        // They will because the group was created when sending to the form


        // Create cache of group usages
        /** @var Group[] $groupCache */
//        $groupCache = [
//            0 => new Group(null, '', null)
//        ];

        // cache groups
        foreach ($groups as $groupId => $groupData) {
            $groupName     = $groupData['name'];
            $groupMaxValue = isset($groupData['maxValue']) ? (float)$groupData['maxValue'] : null;

            $group = $grid->getRowSet()->getGroups()[$groupId]
                ->setName($groupName);

            $groupUsage = (new GroupUsage($group))
                ->setMaxValue($groupMaxValue);

            $grid->getGroupUsages()[$groupId] = $groupUsage;

//            $groupCache[$groupId] = new Group($groupId, $groupName, $groupMaxValue);
        }

        // Cache criteria


        foreach ($criteria as $criterionId => $criterion) {
            $groupId          = $criterion['groupId'];
            $criterionEnabled = isset($criterion['on']) && $criterion['on'];
            $criterionName    = $criterion['name'];


            $group = $grid->getRowSet()->getGroups()[$groupId];

            // spawn
            $criterion = $group->getCriteria()[$criterionId]
                ->setName($criterionName);

//            // set readonliness
//            $criterion->setReadOnly(isset($criterion['readonly']) && $criterion['readonly']);
//
//            // retrieve from cache
//            $group = $grid->getRowSet();
//
//            // cascade readonliness into group
//            if ($criterion->isReadOnly()) {
//                $group->setReadOnly(true);
//            }
//
//            if ($includeEmptyCriteria) {
//                $rubric->putCriterion($group, $criterion);
//            }

            if (!$criterionEnabled) {
                continue;
            }

            foreach ($levelCache as $levelSequence => $l) {

                if (empty($descriptors[$criterionId][$levelSequence])) {
                    continue;
                }

                $descriptor      = $descriptors[$criterionId][$levelSequence];
                $descriptorId    = isset($descriptor['id']) ? $descriptor['id'] : null;
                $descriptorText  = $descriptor['text'];
                $descriptorValue = $descriptor['points'];

                if (strlen($descriptorText) || strlen($descriptorValue)) {
                    $d = new Descriptor($descriptorText, $descriptorValue, $descriptorId);
                    try {
                        $rubric->putDescriptor($group, $criterion, $l, $d);
                    } catch (InvalidArgumentException $e) {
                        echo "Unable to put descriptor";
                    }
                }

            }

        }

        return $grid;
    }
}
