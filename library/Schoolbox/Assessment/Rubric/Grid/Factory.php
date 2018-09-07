<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

use InvalidArgumentException;
use Schoolbox\Assessment\Rubric\RowSet\Capability;
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
        $rubricData   = reset($postData);
        $levels       = $rubricData['level']      ?: [];
        $capabilities = $rubricData['group']      ?: [];
        $indicators   = $rubricData['criterion']  ?: [];
        $descriptors  = $rubricData['descriptor'] ?: [];
        $assessment   = $grid->getAssessment();
        $markPerCap   = $assessment instanceof DueWork && $assessment->getMarkPerCapability();
        $spec         = new ValidCapabilityMax($markPerCap);


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


        // Must assume that all capabilities in formdata exist
        // They will because the group was created when sending to the form


        // Which groups are actually in use?
        $groupIdsInUse = [];
        foreach ($criteria as $criterion) {
            if ($criterion['on']) {
                $groupIdsInUse[$criterion['groupId']] = true;
            }
        }


        // cache capabilities
        /** @var Capability[] $groupCache */
        $groupCache = [];
        foreach ($capabilities as $groupData) {
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
            $group         = $grid->getRowSet()->retrieveCapability($groupId);

            // If $group is null, then that's a problem; we're trying to save
            // usage for a group which doesn't exist


            // Can this group's name be set?
            if (!$group->isInUseExcluding($grid->getAssessment())) {
                $group->setName($groupName);
            }

            if ($spec->isSatisfiedBy($groupMaxValue)) {
                $groupUsage = (new CapabilityUsage($group))->setMaxValue($groupMaxValue);
            } else {
                throw new InvalidArgumentException(sprintf('provide capability maximum points that are numeric and greater than or equal to 0'));
            }


            $groupCache[$groupId]                  = $group;
            $grid->getCapabilityUsages()[$groupId] = $groupUsage;
        }


        // Cache indicators
        foreach ($indicators as $indicatorData) {
            $groupId          = (int)$indicatorData['groupId'];
            $indicatorId      = (int)$indicatorData['id'];
            $indicatorName    = $indicatorData['name'];
            $indicatorEnabled = isset($indicatorData['on']) && $indicatorData['on'];
            if (!$indicatorEnabled) {
                continue;
            }

            // spawn
            $group     = $groupCache[$groupId];
            $indicator = $group->retrieveIndicator($indicatorId);

            // If $indicator is null, then that's a problem; we're trying to
            // save usage for a indicator which doesn't exist


            // Can this indicator's name be set?
            if (!$indicator->isInUseExcluding($grid->getAssessment())) {
                $indicator->setName($indicatorName);
            }


            $indicatorUsage = new IndicatorUsage($indicator);

            foreach ($levelCache as $levelSequence => $level) {

                if (empty($descriptors[$indicatorId][$levelSequence])) {
                    continue;
                }

                $descriptor      = $descriptors[$indicatorId][$levelSequence];
                $descriptorId    = $descriptor['id'] ?? null;
                $descriptorText  = $descriptor['text'];
                $descriptorValue = $descriptor['points'];

                if ('' !== $descriptorText || '' !== $descriptorValue) {
                    $indicatorUsage->getDescriptors()[$levelSequence] =
                        (new Descriptor())
                            ->setId($descriptorId)
                            ->setDescription($descriptorText)
                            ->setMaxValue(null !== $descriptorValue ? (float)$descriptorValue : null);
                }
            }

            $groupUsage                                     = $grid->retrieveCapabilityUsage($group);
            $groupUsage->getIndicatorUsages()[$indicatorId] = $indicatorUsage;
        }

        // Calculate max values for each group usage.
        // If no value was passed: use calculated value
        foreach ($grid->getCapabilityUsages() as $groupUsage) {
            $groupMaxValue = $groupUsage->getMaxValue();
            if (null === $groupMaxValue) {
                $groupUsage->setMaxValue($groupUsage->calculateMaxValueFromIndicators());
            }
        }

        return $grid;
    }
}
