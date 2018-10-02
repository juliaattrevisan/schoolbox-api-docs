<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid\Test;

use Schoolbox\Assessment\Rubric\Grid as RubricGrid;
use Schoolbox\Assessment\Rubric\MarkedGrid as RubricMarkedGrid;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;

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
        // Build a grid and row set
        $grid   = (new RubricGrid\Test\Generator())->generateGrid($capabilities, $levels, $capabilityUsages);
        $rowSet = $grid->getRowSet();


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
                if ($descriptor instanceof RubricGrid\Descriptor) {
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
