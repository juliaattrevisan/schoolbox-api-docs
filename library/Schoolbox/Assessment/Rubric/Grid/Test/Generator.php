<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Test;

use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Rubric\Grid as RubricGrid;
use Schoolbox\Assessment\Rubric\RowSet as RubricRowSet;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;

/**
 * Generator for test data for grid tests.
 */
class Generator
{
    /**
     * Very basic test levels.
     */
    public const TEST_LEVELS = [
        1 => ['name' => 'Poor',],
        2 => ['name' => 'Okay',],
        3 => ['name' => 'Good',],
        4 => ['name' => 'Great',],
        5 => ['name' => 'Excellent',],
    ];


    /**
     * Very basic test capability usages.
     *
     * Uses the capability IDs specified in
     * `\Schoolbox\Assessment\Rubric\RowSet\Test\Generator::TEST_CAPABILITIES`
     * and the levels specified in
     * `\Schoolbox\Assessment\Rubric\Grid\Test\Generator::TEST_LEVELS`.
     */
    public const TEST_CAPABILITY_USAGES = [
        1 => [
            'indicators' => [
                11 => [
                    'descriptors' => [
                        111 => [
                            'levelId'     => 1,
                            'description' => 'Poor',
                            'value'       => 0,
                        ],
                        112 => [
                            'levelId'     => 2,
                            'description' => 'Okay',
                            'value'       => 2,
                        ],
                        114 => [
                            'levelId'     => 4,
                            'description' => 'Great',
                            'value'       => 6,
                        ],
                        115 => [
                            'levelId'     => 5,
                            'description' => 'Excellent',
                            'value'       => 8,
                        ],
                    ],
                ],
                12 => [
                    'descriptors' => [
                        121 => [
                            'levelId'     => 1,
                            'description' => 'Poor',
                            'value'       => 0,
                        ],
                        122 => [
                            'levelId'     => 2,
                            'description' => 'Okay',
                            'value'       => 2,
                        ],
                        123 => [
                            'levelId'     => 3,
                            'description' => 'Good',
                            'value'       => 4,
                        ],
                        125 => [
                            'levelId'     => 5,
                            'description' => 'Excellent',
                            'value'       => 8,
                        ],
                    ],
                ],
            ],
        ],
        2 => [
            'indicators' => [
                22 => [
                    'descriptors' => [
                        222 => [
                            'levelId'     => 2,
                            'description' => 'Okay',
                            'value'       => 2,
                        ],
                        223 => [
                            'levelId'     => 3,
                            'description' => 'Good',
                            'value'       => 4,
                        ],
                        224 => [
                            'levelId'     => 4,
                            'description' => 'Great',
                            'value'       => 6,
                        ],
                        225 => [
                            'levelId'     => 5,
                            'description' => 'Excellent',
                            'value'       => 8,
                        ],
                    ],
                ],
                23 => [
                    'descriptors' => [
                        231 => [
                            'levelId'     => 1,
                            'description' => 'Poor',
                            'value'       => 0,
                        ],
                        233 => [
                            'levelId'     => 3,
                            'description' => 'Good',
                            'value'       => 4,
                        ],
                        234 => [
                            'levelId'     => 4,
                            'description' => 'Great',
                            'value'       => 6,
                        ],
                        235 => [
                            'levelId'     => 4,
                            'description' => 'Excellent',
                            'value'       => 8,
                        ],
                    ],
                ],
            ],
        ],
    ];


    /**
     * The rubric grid will be built around this DueWork instance
     * You can override it post-construction if you need to change its attributes etc.
     *
     * This seemingly-odd shim introduced so that generateGrid() could work on something
     * different the prescribed hardcoded DueWork instance without altering method signatures
     *
     * @var DueWork
     */
    private $dueWork;


    /**
     * Generator constructor.
     */
    public function __construct()
    {
        // Build a grid that uses the row set
        $this->dueWork = (new DueWork())
            ->setMarkType((new MarkType())->setId(MarkType::TYPE_TOTAL))
            ->setMaxMark(100);
    }


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
        $dueWork = $this->dueWork;
        $grid    = new RubricGrid\Grid($dueWork, $rowSet);


        // Create the mark scheme
        $markSchemeFactory = new MarkSchemeFactory();
        $dueWork->setMarkScheme($markSchemeFactory->spawnMarkSchemeFor($dueWork));


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

            foreach ($capabilityUsage['indicators'] ?? [] as $indicatorId => $indicatorUsage) {
                $indicatorUsageObject = new RubricGrid\IndicatorUsage($rowSet->retrieveIndicator($indicatorId));

                // Descriptors
                foreach ($indicatorUsage['descriptors'] ?? [] as $descriptorId => $descriptor) {
                    $indicatorUsageObject->getDescriptors()[$descriptor['levelId']] = (new RubricGrid\Descriptor())
                        ->setId($descriptorId)
                        ->setDescription($descriptor['description'])
                        ->setMinValue($descriptor['minValue'] ?? $descriptor['value'] ?? null)
                        ->setMaxValue($descriptor['maxValue'] ?? $descriptor['value'] ?? null);
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


    /**
     * @param DueWork $dueWork
     * @return $this
     */
    public function setDueWork(DueWork $dueWork): self
    {
        $this->dueWork = $dueWork;
        return $this;
    }
}
