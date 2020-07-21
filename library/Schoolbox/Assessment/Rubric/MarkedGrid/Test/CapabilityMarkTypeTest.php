<?php
/**
 * CapabilityMarkTypeTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Example\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Rubric\Grid\Test\Generator as GridGenerator;
use Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark;
use Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid;
use Schoolbox\Assessment\Rubric\MarkedGrid\Test\Generator;
use Schoolbox\Assessment\Rubric\RowSet\Test\Generator as RowSetGenerator;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\MarkScheme\Factory;

/**
 * Class CapabilityMarkTypeTest
 *
 * Rubric Capabilities' values can be represented with a different Mark Type than its
 * parent Assessment. This should ensure that Capabilities don't inadvertently regress
 * to using their parent Assessment's Mark Type.
 *
 * @package Schoolbox\Example\Test
 */
class CapabilityMarkTypeTest extends TestCase
{

    private const TEST_CAPABILITIES = [
        1 => [
            'name'=> 'Capability 1',
            'indicators' => [
                11 => ['name' => 'Indicator 1.1'],
                12 => ['name' => 'Indicator 1.2'],
                13 => ['name' => 'Indicator 1.3'],
            ],
        ],
        2 => [
            'name' => 'Capability 2',
            'indicators' => [
                21 => ['name' => 'Indicator 2.1'],
                22 => ['name' => 'Indicator 2.2'],
                23 => ['name' => 'Indicator 2.3'],
            ],
        ],
    ];


    private const TEST_LEVELS = [
        1 => ['name' => 'Poor'],
        2 => ['name' => 'Okay'],
        3 => ['name' => 'Good'],
        4 => ['name' => 'Great'],
        5 => ['name' => 'Excellent'],
    ];


    /**
     * Capabilities made available for a simulated DueWork instance
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
     * These are the Descriptors that we simulate as being selected,
     * corresponding to elements in TEST_CAPABILITY_USAGES that were
     * simulated as enabled for a particular DueWork instance
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
     * @dataProvider provider_validExample
     * @param mixed $input
     * @param int   $expected
     */
    public function testValidExample($input, $expected)
    {
        /* Substitute this with a spec pattern or other conditional
         */
        $outcome = false;

        if (is_numeric($input)) {
            $outcome = true;
        }

        /* There are other assertions that you can try (ctrl-click to follow)
         */
        $this->assertEquals($expected, $outcome);
    }


    /**
     * Data provider for testValidExample
     *
     * @return array
     */
    public function provider_validExample() : array {
        $generator        = new Generator();
        $capabilities     = self::TEST_CAPABILITIES;
        $levels           = self::TEST_LEVELS;
        $capabilityUsages = self::TEST_CAPABILITY_USAGES;
        $capabilityMarks  = self::TEST_CAPABILITY_MARKS;

        /* Set up my own assessment; rubric grids only work on DueWork instances
         */
        $dueWorkMarkType = (new MarkType())->setId(MarkType::TYPE_NONE)->setSlug(MarkType::SLUG_NONE);
        $capabilityMarkType = (new MarkType())->setId(MarkType::TYPE_TOTAL)->setSlug(MarkType::SLUG_TOTAL);
        $capabilityGradeGroup = null;

        /* Set up a grid generator with the assessment specified
         */
        $generator->setDueWork((new DueWork\Model())
            ->setMarkType($dueWorkMarkType)
            ->setCapabilityMarkType($capabilityMarkType)
            ->setCapabilityGradeGroup($capabilityGradeGroup));

        /* Reach in and yank out a bunch of human readable marks using our local variant
         */
        $marks = $this->calculateCapabilityMarks($generator->generateMarkedGrid(
            $capabilities,
            $levels,
            $capabilityUsages,
            $capabilityMarks
        ));

        return [

            /* Add other cases that you expect to be valid
             */
            'Valid Example 1' => [
                0,    // goes into testValidExample() as $input
                true  // goes into testValidExample() as $expected
            ],
        ];
    }


    /**
     * @param MarkedGrid    $markedGrid
     * @return array
     * @throws \DomainException
     * @throws \RangeException
     */
    private function calculateCapabilityMarks(MarkedGrid $markedGrid): array
    {
        $stack = [];
        $markSchemeFactory = new Factory();
        $dueWork = $markedGrid->getGrid()->getAssessment();

        foreach ($markedGrid->getCapabilityMarks() as $index => $capabilityMark) {
            $capabilityUsage = $capabilityMark->getCapabilityUsage();

            /* Figure out the mark scheme for this capability
             */
            $capabilityMarkScheme = $markSchemeFactory->spawnForCapability($dueWork, $capabilityUsage->getMaxValue());

            // Mark-per-capability?
            // Use the actual input value (which may be null) instead of the
            // calculated mark.
            $capabilityValue = $markedGrid->getGrid()->isMarkPerCapability() ? $capabilityMark->getValue() : $capabilityMark->calculateScaledMark();

            $stack[$index] = (new CapabilityMark($capabilityUsage, $capabilityMarkScheme))
                ->setValue($capabilityValue)
                ->retrieveHumanReadableMark();
        }

        return $stack;
    }
}
