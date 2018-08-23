<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

use Schoolbox\Assessment\Component\SubmissionBox\Rubric\GroupMapper;
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
//        $groupCache = [];
        foreach ($groups as $groupId => $groupData) {
            $groupName     = $groupData['name'];
            $groupMaxValue = isset($groupData['maxValue']) ? (float)$groupData['maxValue'] : null;

            // Group might not be set
            // TODO handle group name changes
            $group      = $grid->getRowSet()->retrieveGroupByName($groupName);
            $groupUsage = (new GroupUsage($group))
                ->setMaxValue($groupMaxValue);

            $groupCache[$groupId]             = $group;
            $grid->getGroupUsages()[$groupId] = $groupUsage;
        }



        // Cache criteria
        foreach ($criteria as $criterionId => $criterionData) {
            $groupId          = $criterionData['groupId'];
            $criterionEnabled = isset($criterionData['on']) && $criterionData['on'];
            $criterionName    = $criterionData['name'];

            $group = $groupCache[$groupId];

            // spawn
            $criterion = $group->retrieveCriterionByName($criterionName);

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

            $criterionUsage = new CriterionUsage($criterion);

            foreach ($levelCache as $levelSequence => $level) {

                if (empty($descriptors[$criterionId][$levelSequence])) {
                    continue;
                }

                $descriptor      = $descriptors[$criterionId][$levelSequence];
                $descriptorId    = $descriptor['id'] ?? null;
                $descriptorText  = $descriptor['text'];
                $descriptorValue = $descriptor['points'];

                if ('' !== $descriptorText || '' !== $descriptorValue) {
                    $d = (new Descriptor())
                        ->setId($descriptorId)
                        ->setDescription($descriptorText)
                        ->setMaxValue(null !== $descriptorValue ? (float)$descriptorValue : null);
                    $criterionUsage->getDescriptors()[$descriptorId] = $d;
//                    try {
//                        $rubric->putDescriptor($group, $criterion, $level, $d);
//                    } catch (InvalidArgumentException $e) {
//                        echo "Unable to put descriptor";
//                    }
                }
            }

            $groupUsage->getCriterionUsages()[$criterionId] = $criterionUsage;
        }

        return $grid;
    }
}
