<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid\Test;

use Schoolbox\Assessment\Rubric\Grid;
use Schoolbox\Assessment\Rubric\MarkedGrid as RubricMarkedGrid;
use Schoolbox\Assessment\Rubric\RowSet;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Class Generator
 */
class Generator
{
    /**
     * Build a marked grid.
     *
     * @param array $capabilities
     * @param array $levels
     * @param array $capabilityUsages
     * @param array $capabilityMarks
     * @return RubricMarkedGrid\MarkedGrid
     */
    public function generateMarkedGrid(
        array $capabilities,
        array $levels,
        array $capabilityUsages,
        array $capabilityMarks
    ): RubricMarkedGrid\MarkedGrid
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
        $grid    = new Grid\Grid($dueWork, $rowSet);

        // Build and add levels
        foreach ($levels as $levelId => $level) {
            $grid->getLevels()[$levelId] = (new Grid\Level())
                ->setId($levelId)
                ->setName($level['name']);
        }


        // Build capability and indicator usage, and descriptors
        foreach ($capabilityUsages as $capabilityId => $capabilityUsage) {
            $capabilityUsageObject = (new Grid\CapabilityUsage($rowSet->retrieveCapability($capabilityId)))
                ->setMaxValue($capabilityUsage['maxValue']);

            foreach ($capabilityUsages['indicatorUsages'] as $indicatorId => $indicatorUsage) {
                $indicatorUsageObject = new Grid\IndicatorUsage($rowSet->retrieveIndicator($indicatorId));

                // Descriptors
                foreach ($grid->getLevels() as $levelId => $level) {
                    $descriptor = $indicatorUsage['descriptors'][$levelId] ?? null;
                    if (null !== $descriptor) {
                        $indicatorUsageObject->getDescriptors()[$levelId] = (new Grid\Descriptor())
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


        // Build a marked grid
        $feedback   = new DueWorkFeedback();
        $markScheme = new TotalMarkFractional($grid->calculateMaxValueFromCapabilities());
        $markedGrid = new RubricMarkedGrid\MarkedGrid($feedback, $grid, $markScheme);


        // Add cap and ind marks to it
        foreach ($capabilityMarks as $capabilityId => $capabilityMark) {
            $capabilityUsageObject = $grid->retrieveCapabilityUsage($rowSet->retrieveCapability($capabilityId));
            $capabilityMarkObject  = new RubricMarkedGrid\CapabilityMark(
                $capabilityUsageObject,
                new TotalMarkFractional($capabilityUsageObject->getMaxValue())
            );
            $capabilityMarkObject->setValue($capabilityMark['value']);

            foreach ($capabilityMarks['indicatorMarks'] as $indicatorId => $indicatorMark) {
                $indicatorUsageObject = $grid->retrieveIndicatorUsage($rowSet->retrieveIndicator($indicatorId));
                $indicatorMarkObject  = new RubricMarkedGrid\IndicatorMark($indicatorUsageObject);

                // Which descriptor is selected?
                $descriptor = null;
                $markValue  = $indicatorMark['value'] ?? null;
                if ($indicatorMark['descriptorId']) {
                    $descriptor = $indicatorUsageObject->getDescriptors()->getBy('id', $indicatorMark['descriptorId']);
                }
                if ($descriptor instanceof Grid\Descriptor) {
                    $indicatorMarkObject->setDescriptor($descriptor);
                    $markValue = $markValue ?? $descriptor->getMaxValue();
                }

                // Set value depending on descriptor
                $indicatorMarkObject->setValue($markValue);

                $capabilityMarkObject->getIndicatorMarks()[$indicatorId] = $indicatorMarkObject;
            }

            $markedGrid->getCapabilityMarks()[$capabilityId] = $capabilityMarkObject;
        }

        return $markedGrid;
    }
}
