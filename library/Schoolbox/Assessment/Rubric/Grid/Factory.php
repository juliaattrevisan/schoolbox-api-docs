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


        // Must assume that all capabilities in formdata exist:
        // they will, because it was created when the capability was added
        // via the form.


        // Which capabilities are actually in use?
        $capabilityIdsInUse = [];
        foreach ($indicators as $indicator) {
            if ($indicator['on']) {
                $capabilityIdsInUse[$indicator['groupId']] = true;
            }
        }


        // cache capabilities
        /** @var Capability[] $capabilityCache */
        $capabilityCache = [];
        foreach ($capabilities as $capability) {
            // This capability must exist and be in use in order for it to be saved
            $capabilityId   = (int)$capability['id'];
            $capabilityName = $capability['name'];
            if (!$capabilityId) {
                continue;
            }
            if (empty($capabilityIdsInUse[$capabilityId])) {
                continue;
            }
            $capabilityMaxValue = isset($capability['maxValue']) ? (float)$capability['maxValue'] : null;
            $capability         = $grid->getRowSet()->retrieveCapability($capabilityId);

            // If $group is null, then that's a problem; we're trying to save
            // usage for a group which doesn't exist


            // Can this group's name be set?
            if (!$capability->isInUseExcluding($grid->getAssessment())) {
                $capability->setName($capabilityName);
            }

            if ($spec->isSatisfiedBy($capabilityMaxValue)) {
                $capabilityUsage = (new CapabilityUsage($capability))->setMaxValue($capabilityMaxValue);
            } else {
                throw new InvalidArgumentException(sprintf('provide capability maximum points that are numeric and greater than or equal to 0'));
            }


            $capabilityCache[$capabilityId]             = $capability;
            $grid->getCapabilityUsages()[$capabilityId] = $capabilityUsage;
        }


        // Cache indicators
        foreach ($indicators as $indicatorData) {
            $capabilityId     = (int)$indicatorData['groupId'];
            $indicatorId      = (int)$indicatorData['id'];
            $indicatorName    = $indicatorData['name'];
            $indicatorEnabled = isset($indicatorData['on']) && $indicatorData['on'];
            if (!$indicatorEnabled) {
                continue;
            }

            // spawn
            $capability = $capabilityCache[$capabilityId];
            $indicator  = $capability->retrieveIndicator($indicatorId);

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

            $capabilityUsage                                     = $grid->retrieveCapabilityUsage($capability);
            $capabilityUsage->getIndicatorUsages()[$indicatorId] = $indicatorUsage;
        }

        // Calculate max values for each capability usage.
        // If no value was passed: use calculated value
        foreach ($grid->getCapabilityUsages() as $capabilityUsage) {
            $capabilityMaxValue = $capabilityUsage->getMaxValue();
            if (null === $capabilityMaxValue) {
                $capabilityUsage->setMaxValue($capabilityUsage->calculateMaxValueFromIndicators());
            }
        }

        return $grid;
    }
}
