<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\RowSet\Test;

use Schoolbox\Assessment\Rubric\RowSet as RubricRowSet;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Generator for test data for row set tests.
 */
class Generator
{
    /**
     * Very basic test capabilities.
     */
    public const TEST_CAPABILITIES = [
        1 => [
            'name'       => 'Capability 1',
            'indicators' => [
                11 => ['name' => 'Indicator 1.1'],
                12 => ['name' => 'Indicator 1.2'],
                13 => ['name' => 'Indicator 1.3'],
            ],
        ],
        2 => [
            'name'       => 'Capability 2',
            'indicators' => [
                21 => ['name' => 'Indicator 2.1'],
                22 => ['name' => 'Indicator 2.2'],
                23 => ['name' => 'Indicator 2.3'],
            ],
        ],
    ];


    /**
     * Build a row set.
     *
     * @param array $capabilities The capabilities that comprise the row set.
     *                            [$capabilityId => [
     *                              'name'       => name,
     *                              'indicators' => [
     *                                  $indicatorId => [
     *                                      'name' => name,
     *                                  ]
     *                              ],
     *                            ]]
     *
     * @return RubricRowSet\RowSet
     */
    public function generateRowSet(array $capabilities): RubricRowSet\RowSet
    {
        // Build the row set
        $folder = new FolderModel();
        $rowSet = new RubricRowSet\RowSet($folder);

        // Build caps and indicators
        foreach ($capabilities as $capabilityId => $capability) {
            $capabilityObject = (new RubricRowSet\Capability())
                ->setId($capabilityId)
                ->setName($capability['name']);
            foreach ($capability['indicators'] as $indicatorId => $indicator) {
                $indicatorObject = (new RubricRowSet\Indicator())
                    ->setId($indicatorId)
                    ->setName($indicator['name']);
                $capabilityObject->getIndicators()[$indicatorId] = $indicatorObject;
            }

            $rowSet->getCapabilities()[$capabilityId] = $capabilityObject;
        }

        return $rowSet;
    }
}
