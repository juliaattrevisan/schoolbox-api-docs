<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

/**
 * Class Factory
 */
class Factory
{
    /**
     * Port of `\Schoolbox\Assessment\Rubric\Factory::buildFromPostData`,
     * but builds a `Grid` object instead.
     *
     * @param Grid  $grid A grid (which already contains an assessment and rowset)
     * @param array $postData
     * @return Grid
     */
    public function buildFromPostData(Grid $grid, array $postData): Grid
    {
        // we're only going to take the first one... this could be converted to foreach easily enough
        $rubricData = reset($postData);

        $levels      = $rubricData['level']      ?: [];
        $groups      = $rubricData['group']      ?: [];
        $criteria    = $rubricData['criterion']  ?: [];
        $descriptors = $rubricData['descriptor'] ?: [];


        /** @var Level[] $levelCache */
        $levelCache = [];

        // cache levels and ensure all exist in the rubric
        foreach ($levels as $levelSequence => $level) {
            $levelName = $level['name'];
            $levelId   = $level['id'] ?? null;

            $levelCache[$levelSequence] = (new Level())
                ->setId($levelId)
                ->setName($levelName);

            $grid->getLevels()->add($levelCache[$levelSequence]);
        }


        // Must assume that all groups in formdata exist
        // They will because the group was created when sending to the form


        // cache groups
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
                    $criterionUsage->getDescriptors()[$levelSequence] = $d;
                }
            }

            $groupUsage = $grid->retrieveGroupUsage($group);
            $groupUsage->getCriterionUsages()[$criterionId] = $criterionUsage;
        }

        // Calculate max values for each group usage.
        // If no value was passed: use calculated value
        foreach ($grid->getGroupUsages() as $groupUsage) {
            $groupMaxValue = $groupUsage->getMaxValue();
            if (null === $groupMaxValue) {
                $groupUsage->setMaxValue($groupUsage->calculateMaxValueFromCriteria());
            }
        }

        return $grid;
    }
}
