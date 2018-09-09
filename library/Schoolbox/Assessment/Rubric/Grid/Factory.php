<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

use InvalidArgumentException;
use Schoolbox\Assessment\Rubric\RowSet\Group;
use Schoolbox\Assessment\Rubric\ValidCapabilityMax;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;

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
        $rubricData  = reset($postData);
        $levels      = $rubricData['level']      ?: [];
        $groups      = $rubricData['group']      ?: [];
        $criteria    = $rubricData['criterion']  ?: [];
        $descriptors = $rubricData['descriptor'] ?: [];
        $assessment  = $grid->getAssessment();
        $markPerCap  = $assessment instanceof DueWork && $assessment->getMarkPerCapability();
        $spec        = new ValidCapabilityMax($markPerCap);


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


        // Which groups are actually in use?
        $groupIdsInUse = [];
        foreach ($criteria as $criterion) {
            if ($criterion['on']) {
                $groupIdsInUse[$criterion['groupId']] = true;
            }
        }


        // cache groups
        /** @var Group[] $groupCache */
        $groupCache = [];
        foreach ($groups as $groupData) {
            // This group must exist and be in use in order for it to be saved
            $groupId   = (int)$groupData['id'];
            $groupName = $groupData['name'];
            if (!$groupId) {
                continue;
            }
            if (empty($groupIdsInUse[$groupId])) {
                continue;
            }
            $groupMaxValue = isset($groupData['maxValue']) ? (float)$groupData['maxValue'] : null;
            $group         = $grid->getRowSet()->retrieveGroup($groupId);

            // If $group is null, then that's a problem; we're trying to save
            // usage for a group which doesn't exist


            // Can this group's name be set?
            if (!$group->isInUseExcluding($grid->getAssessment())) {
                $group->setName($groupName);
            }

            if ($spec->isSatisfiedBy($groupMaxValue)) {
                $groupUsage = (new GroupUsage($group))->setMaxValue($groupMaxValue);
            } else {
                throw new InvalidArgumentException(sprintf('provide capability maximum points that are numeric and greater than or equal to 0'));
            }


            $groupCache[$groupId]             = $group;
            $grid->getGroupUsages()[$groupId] = $groupUsage;
        }


        // Cache criteria
        foreach ($criteria as $criterionData) {
            $groupId          = (int)$criterionData['groupId'];
            $criterionId      = (int)$criterionData['id'];
            $criterionName    = $criterionData['name'];
            $criterionEnabled = isset($criterionData['on']) && $criterionData['on'];
            if (!$criterionEnabled) {
                continue;
            }

            // spawn
            $group     = $groupCache[$groupId];
            $criterion = $group->retrieveCriterion($criterionId);

            // If $criterion is null, then that's a problem; we're trying to
            // save usage for a criterion which doesn't exist


            // Can this criterion's name be set?
            if (!$criterion->isInUseExcluding($grid->getAssessment())) {
                $criterion->setName($criterionName);
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
                    $criterionUsage->getDescriptors()[$levelSequence] =
                        (new Descriptor())
                            ->setId($descriptorId)
                            ->setDescription($descriptorText)
                            ->setMaxValue(null !== $descriptorValue ? (float)$descriptorValue : null);
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
