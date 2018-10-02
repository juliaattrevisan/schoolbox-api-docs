<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Test;

use Schoolbox\Assessment\Rubric\Grid as RubricGrid;
use Schoolbox\Assessment\Rubric\RowSet as RubricRowSet;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;

/**
 * Generator for test data for grid tests.
 */
class Generator
{
    /**
     * Build a grid.
     *
     * @param array $capabilities     The capabilities that comprise the grid's
     *                                row set.
     *                                (@see `\Schoolbox\Assessment\Rubric\RowSet\Test\Generator::generateRowSet`)
     * @param array $levels           The levels in this rubric grid.
     *                                [$levelId => ['name' => name]]
     * @param array $capabilityUsages The usages of capabilities in this grid.
     *                                [$capabilityId => [
     *                                  'maxValue'   => capability max (inferred if not set),
     *                                  'indicators' => [$indicatorId => [
     *                                      'descriptors' => [$descriptorId => [
     *                                          'levelId'     => level ID
     *                                          'description' => description
     *                                          'value'       => descriptor value
     *                                          'minValue'    => descriptor min (value if not set)
     *                                          'maxValue'    => descriptor max (value if not set)
     *                                      ]],
     *                                  ]],
     *                                ]]
     *
     * @return RubricGrid\Grid
     */
    public function generateGrid(array $capabilities, array $levels, array $capabilityUsages): RubricGrid\Grid
    {
        // Build a row set
        $rowSet = (new RubricRowSet\Test\Generator())->generateRowSet($capabilities);


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
            $capabilityUsageObject = new RubricGrid\CapabilityUsage($rowSet->retrieveCapability($capabilityId));
            $capabilityMaxValue    = $capabilityUsage['maxValue'] ?? null;

            foreach ($capabilityUsages['indicatorUsages'] as $indicatorId => $indicatorUsage) {
                $indicatorUsageObject = new RubricGrid\IndicatorUsage($rowSet->retrieveIndicator($indicatorId));

                // Descriptors
                foreach ($indicatorUsage['descriptors'] as $descriptorId => $descriptor) {
                    $indicatorUsageObject->getDescriptors()[$descriptor['levelId']] = (new RubricGrid\Descriptor())
                        ->setId($descriptorId)
                        ->setDescription($descriptor['description'])
                        ->setMinValue($descriptor['minValue'] ?? $descriptor['value'])
                        ->setMaxValue($descriptor['maxValue'] ?? $descriptor['value']);
                }

                $capabilityUsageObject->getIndicatorUsages()[$indicatorId] = $indicatorUsageObject;
            }

            $capabilityUsageObject->setMaxValue(
                $capabilityMaxValue ?? $capabilityUsageObject->calculateMaxValueFromIndicators()
            );

            $grid->getCapabilityUsages()[$capabilityId] = $capabilityUsageObject;
        }

        return $grid;
    }
}
