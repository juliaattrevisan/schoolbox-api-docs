<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid\Test;

use Schoolbox\Assessment\Rubric\Grid as RubricGrid;
use Schoolbox\Assessment\Rubric\MarkedGrid as RubricMarkedGrid;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
/**
 * Generator for test data for marked grid tests.
 */
class Generator
{
    /**
     * Very basic capability usages.
     *
     * Uses the capability IDs specified in
     * `\Schoolbox\Assessment\Rubric\RowSet\Test\Generator::TEST_CAPABILITIES`
     * and the descriptor IDs specified in
     * `\Schoolbox\Assessment\Rubric\Grid\Test\Generator::TEST_CAPABILITY_USAGES`.
     */
    public const TEST_CAPABILITY_MARKS = [
        1 => [
            'indicators' => [
                11 => [
                    // inferred value: 0
                    'descriptorId' => 111,
                ],
                12 => [
                    // inferred value: 8
                    'descriptorId' => 125,
                ],
            ],
        ],
        2 => [
            'indicators' => [
                22 => [
                    // inferred value: 6
                    'descriptorId' => 224,
                ],
                23 => [
                    // inferred value: 0
                ],
            ],
        ],
    ];


    /**
     * Build a marked grid.
     *
     * @param array $capabilities     The capabilities that comprise the grid's
     *                                row set.
     *                                (@see `\Schoolbox\Assessment\Rubric\RowSet\Test\Generator::generateRowSet`)
     * @param array $levels           The levels in this rubric grid.
     *                                (@see `\Schoolbox\Assessment\Rubric\Grid\Test\Generator::generateGrid`)
     * @param array $capabilityUsages The usages of capabilities in this grid.
     *                                (@see `\Schoolbox\Assessment\Rubric\Grid\Test\Generator::generateGrid`)
     * @param array $capabilityMarks  The marks given for capabilities in this grid.
     *                                [$capabilityId => [
     *                                  'value'      => mark value (inferred from indicator marks if not set)
     *                                  'indicators' => [$indicatorId => [
     *                                      'value'        => mark value (inferred from descriptor if not set)
     *                                      'descriptorId' => selected descriptor
     *                                  ]],
     *                                ]]
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


        // Build the factory and collection of mark objects
        $markSchemeFactory     = new MarkSchemeFactory();
        $capabilityMarkObjects = new GenericCollection(RubricMarkedGrid\CapabilityMark::class);


        // Add cap and ind marks to it
        foreach ($capabilityMarks as $capabilityId => $capabilityMark) {
            $capabilityUsageObject = $grid->retrieveCapabilityUsage($rowSet->retrieveCapability($capabilityId));
            $capabilityMaxValue    = $capabilityUsageObject->getMaxValue();
            $capabilityMarkScheme  = $markSchemeFactory->spawnForCapability($grid->getAssessment(), $capabilityMaxValue);
            $capabilityMarkObject  = new RubricMarkedGrid\CapabilityMark(
                $capabilityUsageObject,
                $capabilityMarkScheme
            );
            $capabilityMarkValue = $capabilityMark['value'] ?? null;

            foreach ($capabilityMark['indicators'] as $indicatorId => $indicatorMark) {
                $indicatorUsageObject = $grid->retrieveIndicatorUsage($rowSet->retrieveIndicator($indicatorId));
                $indicatorMarkObject  = new RubricMarkedGrid\IndicatorMark($indicatorUsageObject);

                // Which descriptor is selected?
                $descriptor         = null;
                $indicatorMarkValue = $indicatorMark['value'] ?? null;
                if ($indicatorMark['descriptorId'] ?? null) {
                    $descriptor = $indicatorUsageObject->getDescriptors()->getBy('id', $indicatorMark['descriptorId']);
                }
                if ($descriptor instanceof RubricGrid\Descriptor) {
                    $indicatorMarkObject->setDescriptor($descriptor);
                    $indicatorMarkValue = $indicatorMarkValue ?? $descriptor->getMaxValue();
                }

                // Set value depending on descriptor
                $indicatorMarkObject->setValue($indicatorMarkValue);

                $capabilityMarkObject->getIndicatorMarks()[$indicatorId] = $indicatorMarkObject;
            }

            // If $capabilityMarkValue is null:
            // set the value based on scaling factor, but only if the capability
            // mark has indicator marks: otherwise leave it as null
            if (!isset($capabilityMarkValue)) {
                $indicatorMarkSum = $capabilityMarkObject->calculateIndicatorMarkSum();
                if (isset($indicatorMarkSum)) {
                    $indicatorMaxValue = $capabilityUsageObject->calculateMaxValueFromIndicators();
                    $scalingFactor     = $indicatorMaxValue
                        ? $capabilityMaxValue / $indicatorMaxValue
                        : 0
                    ;
                    $capabilityMarkValue = $indicatorMarkSum * $scalingFactor;
                }
            }

            $capabilityMarkObject->setValue($capabilityMarkValue);

            $capabilityMarkObjects[$capabilityId] = $capabilityMarkObject;
        }

        // Build a marked grid
        $markedGrid = new RubricMarkedGrid\MarkedGrid(
            new DueWorkFeedback(),
            $grid,
            $grid->getAssessment()->getMarkScheme()
        );
        $markedGrid->setCapabilityMarks($capabilityMarkObjects);
        return $markedGrid;
    }
}
