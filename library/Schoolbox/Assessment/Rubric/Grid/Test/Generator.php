<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Test;

use Schoolbox\Assessment\Rubric\Grid as RubricGrid;
use Schoolbox\Assessment\Rubric\RowSet;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Class Generator
 */
class Generator
{
    /**
     * Build a grid.
     *
     * @param array $capabilities
     * @param array $levels
     * @param array $capabilityUsages
     *
     * @return RubricGrid\Grid
     */
    public function generateGrid(array $capabilities, array $levels, array $capabilityUsages): RubricGrid\Grid
    {
        // Build the row set
        $folder = new FolderModel();
        $rowSet = new Rowset\RowSet($folder);

        // Build caps and indicators
        foreach ($capabilities as $capabilityId => $capability) {
            $capabilityObject = (new RowSet\Capability())
                ->setId($capabilityId)
                ->setName($capability['name']);
            foreach ($capability['indicators'] as $indicatorId => $indicator) {
                $indicatorObject = (new RowSet\Indicator())
                    ->setId($indicatorId)
                    ->setName($indicator['name']);
                $capabilityObject->getIndicators()[$indicatorId] = $indicatorObject;
            }

            $rowSet->getCapabilities()[$capabilityId] = $capabilityObject;
        }


        // Build a grid that uses the row set
        $dueWork = new DueWork();
        $grid    = new RubricGrid\Grid($dueWork, $rowSet);

        // Build and add levels
        foreach ($levels as $levelId => $level) {
            $grid->getLevels()[$levelId] = (new RubricGrid\Level())
                ->setId($levelId)
                ->setName($level['name']);
        }


        // Build capability and indicator usage, and descriptors
        foreach ($capabilityUsages as $capabilityId => $capabilityUsage) {
            $capabilityUsageObject = (new RubricGrid\CapabilityUsage($rowSet->retrieveCapability($capabilityId)))
                ->setMaxValue($capabilityUsage['maxValue']);

            foreach ($capabilityUsages['indicatorUsages'] as $indicatorId => $indicatorUsage) {
                $indicatorUsageObject = new RubricGrid\IndicatorUsage($rowSet->retrieveIndicator($indicatorId));

                // Descriptors
                foreach ($grid->getLevels() as $levelId => $level) {
                    $descriptor = $indicatorUsage['descriptors'][$levelId] ?? null;
                    if (null !== $descriptor) {
                        $indicatorUsageObject->getDescriptors()[$levelId] = (new RubricGrid\Descriptor())
                            ->setId($levelId)
                            ->setDescription($descriptor['description'])
                            ->setMinValue($descriptor['minValue'] ?? $descriptor['value'])
                            ->setMaxValue($descriptor['maxValue'] ?? $descriptor['value']);
                    }
                }

                $capabilityUsageObject->getIndicatorUsages()[$indicatorId] = $indicatorUsageObject;
            }

            $grid->getCapabilityUsages()[$capabilityId] = $capabilityUsageObject;
        }

        return $grid;
    }
}
