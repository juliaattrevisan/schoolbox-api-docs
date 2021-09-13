<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid\Test;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RangeException;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;
use Schoolbox\Assessment\Rubric\Grid\CapabilityUsage;
use Schoolbox\Assessment\Rubric\Grid\IndicatorUsage;
use Schoolbox\Assessment\Rubric\Grid\Test\Generator as GridGenerator;
use Schoolbox\Assessment\Rubric\MarkedGrid as RubricMarkedGrid;
use Schoolbox\Assessment\Rubric\RowSet\Test\Generator as RowSetGenerator;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\MarkScheme;

/**
 * @covers \Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark
 */
class CapabilityMark extends TestCase
{
    /**
     * Test method: null out indicator usage data
     *
     * @param array $indicatorUsages
     * @param bool  $nullDescriptors Are descriptors also set to null?
     *
     * @return array
     */
    private function createNullTestIndicatorUsageData(array $indicatorUsages, bool $nullDescriptors): array
    {
        unset($indicatorUsages['maxValue']);
        if ($nullDescriptors) {
            foreach ($indicatorUsages['indicators'] as &$indicator) {
                foreach ($indicator['descriptors'] as &$descriptor) {
                    unset($descriptor['value'], $descriptor['minValue'], $descriptor['maxValue']);
                }
                unset($descriptor);
            }
            unset($indicator);
        }
        return $indicatorUsages;
    }


    /**
     * Data provider for `testCalculateScaledMark`.
     *
     * @return iterable
     */
    public function getTestCalculateScaledMarkData(): iterable
    {
        $generator        = new Generator();
        $capabilities     = RowSetGenerator::TEST_CAPABILITIES;
        $levels           = GridGenerator::TEST_LEVELS;
        $capabilityUsages = GridGenerator::TEST_CAPABILITY_USAGES;
        $capabilityMarks  = Generator::TEST_CAPABILITY_MARKS;


        // No scaling on cap marks for this grid
        $markedGrid = $generator->generateMarkedGrid(
            $capabilities,
            $levels,
            $capabilityUsages,
            $capabilityMarks
        );
        yield 'Capability mark 1 (unscaled)' => [$markedGrid->getCapabilityMarks()[1], 8];
        yield 'Capability mark 2 (unscaled)' => [$markedGrid->getCapabilityMarks()[2], 6];


        // The same grid, with customised capability maximums
        $scaledCapabilityUsages = $capabilityUsages;
        $scaledCapabilityUsages[1]['maxValue'] = 8;
        $scaledCapabilityUsages[2]['maxValue'] = 32;
        $markedGrid = $generator->generateMarkedGrid(
            $capabilities,
            $levels,
            $scaledCapabilityUsages,
            $capabilityMarks
        );
        yield 'Capability mark 1 (scaled 0.5x)' => [$markedGrid->getCapabilityMarks()[1], 4];
        yield 'Capability mark 2 (scaled 2.0x)' => [$markedGrid->getCapabilityMarks()[2], 12];


        // The same grid, with null values
        $nullCapabilityUsages = $capabilityUsages;
        $nullCapabilityUsages[1] = $this->createNullTestIndicatorUsageData($nullCapabilityUsages[1], true);
        $nullCapabilityUsages[2] = $this->createNullTestIndicatorUsageData($nullCapabilityUsages[2], false);
        $markedGrid = $generator->generateMarkedGrid(
            $capabilities,
            $levels,
            $nullCapabilityUsages,
            $capabilityMarks
        );
        yield 'Capability mark 1 (null descriptors)'  => [$markedGrid->getCapabilityMarks()[1], null];
        yield 'Capability mark 2 (null capabilities)' => [$markedGrid->getCapabilityMarks()[2], 6];
    }


    /**
     * @dataProvider getTestCalculateScaledMarkData
     * @covers \Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark::calculateScaledMark
     *
     * @param RubricMarkedGrid\CapabilityMark $capabilityMark
     * @param null|float                      $expected
     */
    public function testCalculateScaledMark(RubricMarkedGrid\CapabilityMark $capabilityMark, ?float $expected): void
    {
        $this->assertSame($expected, $capabilityMark->calculateScaledMark());
    }


    /**
     * Data provider for `testCalculateIndicatorMarkSum`.
     *
     * @return iterable
     */
    public function getTestCalculateIndicatorMarkSumData(): iterable
    {
        $generator        = new Generator();
        $capabilities     = RowSetGenerator::TEST_CAPABILITIES;
        $levels           = GridGenerator::TEST_LEVELS;
        $capabilityUsages = GridGenerator::TEST_CAPABILITY_USAGES;
        $capabilityMarks  = Generator::TEST_CAPABILITY_MARKS;


        // No scaling on cap marks for this grid
        $markedGrid = $generator->generateMarkedGrid(
            $capabilities,
            $levels,
            $capabilityUsages,
            $capabilityMarks
        );
        yield 'Capability mark 1 (unscaled)' => [$markedGrid->getCapabilityMarks()[1], 8];
        yield 'Capability mark 2 (unscaled)' => [$markedGrid->getCapabilityMarks()[2], 6];


        // The same grid, with scaling factors applied: scaling is ignored
        $scaledCapabilityUsages = $capabilityUsages;
        $scaledCapabilityUsages[1]['maxValue'] = 8;
        $scaledCapabilityUsages[2]['maxValue'] = 32;
        $markedGrid = $generator->generateMarkedGrid(
            $capabilities,
            $levels,
            $scaledCapabilityUsages,
            $capabilityMarks
        );
        yield 'Capability mark 1 (scaled 0.5x)' => [$markedGrid->getCapabilityMarks()[1], 8];
        yield 'Capability mark 2 (scaled 2.0x)' => [$markedGrid->getCapabilityMarks()[2], 6];


        // The same grid, with null values
        $nullCapabilityUsages = $capabilityUsages;
        $nullCapabilityUsages[1] = $this->createNullTestIndicatorUsageData($nullCapabilityUsages[1], true);
        $nullCapabilityUsages[2] = $this->createNullTestIndicatorUsageData($nullCapabilityUsages[2], false);
        $markedGrid = $generator->generateMarkedGrid(
            $capabilities,
            $levels,
            $nullCapabilityUsages,
            $capabilityMarks
        );
        yield 'Capability mark 1 (null descriptors)'  => [$markedGrid->getCapabilityMarks()[1], null];
        yield 'Capability mark 2 (null capabilities)' => [$markedGrid->getCapabilityMarks()[2], 6];
    }


    /**
     * @dataProvider getTestCalculateIndicatorMarkSumData
     * @covers \Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark::calculateIndicatorMarkSum
     *
     * @param RubricMarkedGrid\CapabilityMark $capabilityMark
     * @param null|float                      $expected
     */
    public function testCalculateIndicatorMarkSum(
        RubricMarkedGrid\CapabilityMark $capabilityMark,
        ?float $expected
    ): void {
        $this->assertSame($expected, $capabilityMark->calculateIndicatorMarkSum());
    }


    /**
     * Data provider for `testRetrieveNormalizedMark`.
     *
     * @return iterable
     */
    public function getTestRetrieveNormalizedMarkData(): iterable
    {
        $generator        = new Generator();
        $capabilities     = RowSetGenerator::TEST_CAPABILITIES;
        $levels           = GridGenerator::TEST_LEVELS;
        $capabilityUsages = GridGenerator::TEST_CAPABILITY_USAGES;
        $capabilityMarks  = Generator::TEST_CAPABILITY_MARKS;


        // No scaling on cap marks for this grid
        $markedGrid = $generator->generateMarkedGrid(
            $capabilities,
            $levels,
            $capabilityUsages,
            $capabilityMarks
        );
        yield 'Capability mark 1 (unscaled)' => [$markedGrid->getCapabilityMarks()[1], 50];
        yield 'Capability mark 2 (unscaled)' => [$markedGrid->getCapabilityMarks()[2], 37.5];


        // The same grid, with custom capability maximums: scaling is ignored
        $scaledCapabilityUsages = $capabilityUsages;
        $scaledCapabilityUsages[1]['maxValue'] = 8;
        $scaledCapabilityUsages[2]['maxValue'] = 32;
        $markedGrid = $generator->generateMarkedGrid(
            $capabilities,
            $levels,
            $scaledCapabilityUsages,
            $capabilityMarks
        );
        yield 'Capability mark 1 (scaled 0.5x)' => [$markedGrid->getCapabilityMarks()[1], 50];
        yield 'Capability mark 2 (scaled 2.0x)' => [$markedGrid->getCapabilityMarks()[2], 37.5];


        // The same grid, with null values
        $nullCapabilityUsages = $capabilityUsages;
        $nullCapabilityUsages[1] = $this->createNullTestIndicatorUsageData($nullCapabilityUsages[1], true);
        $nullCapabilityUsages[2] = $this->createNullTestIndicatorUsageData($nullCapabilityUsages[2], false);
        $markedGrid = $generator->generateMarkedGrid(
            $capabilities,
            $levels,
            $nullCapabilityUsages,
            $capabilityMarks
        );
        yield 'Capability mark 1 (null descriptors)'  => [$markedGrid->getCapabilityMarks()[1], null];
        yield 'Capability mark 2 (null capabilities)' => [$markedGrid->getCapabilityMarks()[2], 37.5];
    }


    /**
     * @dataProvider getTestRetrieveNormalizedMarkData
     * @covers \Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark::retrieveNormalizedMark
     *
     * @param RubricMarkedGrid\CapabilityMark $capabilityMark
     * @param null|float                      $expected
     */
    public function testRetrieveNormalizedMark(
        RubricMarkedGrid\CapabilityMark $capabilityMark,
        ?float $expected
    ): void {
        $this->assertSame($expected, $capabilityMark->retrieveNormalizedMark());
    }


    /**
     * Data provider for `testIterateIndicatorUsages`.
     *
     * @return iterable
     */
    public function getTestIterateIndicatorUsagesData(): iterable
    {
        $generator        = new Generator();
        $capabilities     = RowSetGenerator::TEST_CAPABILITIES;
        $levels           = GridGenerator::TEST_LEVELS;
        $capabilityUsages = GridGenerator::TEST_CAPABILITY_USAGES;
        $capabilityMarks  = Generator::TEST_CAPABILITY_MARKS;
        $markedGrid       = $generator->generateMarkedGrid(
            $capabilities,
            $levels,
            $capabilityUsages,
            $capabilityMarks
        );

        // Iterating the indicator usages gets me:
        yield 'Capability mark 1' => [
            $markedGrid->getCapabilityMarks()[1],
            [
                $markedGrid->getGrid()->getCapabilityUsages()[1]->getIndicatorUsages()[11],
                $markedGrid->getGrid()->getCapabilityUsages()[1]->getIndicatorUsages()[12],
            ]
        ];
        yield 'Capability mark 2' => [
            $markedGrid->getCapabilityMarks()[2],
            [
                $markedGrid->getGrid()->getCapabilityUsages()[2]->getIndicatorUsages()[22],
                $markedGrid->getGrid()->getCapabilityUsages()[2]->getIndicatorUsages()[23],
            ]
        ];
    }


    /**
     * @dataProvider getTestIterateIndicatorUsagesData
     * @covers \Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark::iterateIndicatorUsages
     *
     * @param RubricMarkedGrid\CapabilityMark $capabilityMark
     * @param array                           $expected
     */
    public function testIterateIndicatorUsages(
        RubricMarkedGrid\CapabilityMark $capabilityMark,
        array $expected
    ): void {
        $actual = [];
        foreach ($capabilityMark->iterateIndicatorUsages() as $indicator) {
            $actual[] = $indicator;
        }
        $this->assertSame($expected, $actual);
    }


    /**
     * Data provider for `testRetrieveIndicatorMark`.
     *
     * @return iterable
     */
    public function getTestRetrieveIndicatorMarkData(): iterable
    {
        $generator        = new Generator();
        $capabilities     = RowSetGenerator::TEST_CAPABILITIES;
        $levels           = GridGenerator::TEST_LEVELS;
        $capabilityUsages = GridGenerator::TEST_CAPABILITY_USAGES;
        $capabilityMarks  = Generator::TEST_CAPABILITY_MARKS;
        $markedGrid       = $generator->generateMarkedGrid(
            $capabilities,
            $levels,
            $capabilityUsages,
            $capabilityMarks
        );

        yield 'Capability mark 1, indicator usage 11' => [
            $markedGrid->getCapabilityMarks()[1],
            $markedGrid->getGrid()->getCapabilityUsages()[1]->getIndicatorUsages()[11],
            $markedGrid->getCapabilityMarks()[1]->getIndicatorMarks()[11]
        ];
        yield 'Capability mark 1, indicator usage 12' => [
            $markedGrid->getCapabilityMarks()[1],
            $markedGrid->getGrid()->getCapabilityUsages()[1]->getIndicatorUsages()[12],
            $markedGrid->getCapabilityMarks()[1]->getIndicatorMarks()[12]
        ];
        yield 'Capability mark 1, indicator usage 22' => [
            $markedGrid->getCapabilityMarks()[1],
            $markedGrid->getGrid()->getCapabilityUsages()[2]->getIndicatorUsages()[22],
            null
        ];
        yield 'Capability mark 1, indicator usage 23' => [
            $markedGrid->getCapabilityMarks()[1],
            $markedGrid->getGrid()->getCapabilityUsages()[2]->getIndicatorUsages()[23],
            null
        ];
    }


    /**
     * @dataProvider getTestRetrieveIndicatorMarkData
     * @covers \Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark::retrieveIndicatorMark
     *
     * @param RubricMarkedGrid\CapabilityMark     $capabilityMark
     * @param IndicatorUsage                      $indicatorUsage
     * @param null|RubricMarkedGrid\IndicatorMark $expected
     */
    public function testRetrieveIndicatorMark(
        RubricMarkedGrid\CapabilityMark $capabilityMark,
        IndicatorUsage $indicatorUsage,
        ?RubricMarkedGrid\IndicatorMark $expected
    ): void {
        $this->assertSame($expected, $capabilityMark->retrieveIndicatorMark($indicatorUsage));
    }


    /**
     * Data provider for `testConvertFormInputToValue`
     *
     * @return iterable
     */
    public function getTestConvertFormInputToValueData(): iterable
    {
        $testCapabilityUsage = $this->buildTestFormInputConversionCapabilityUsage();

        // None
        $none = new RubricMarkedGrid\CapabilityMark($testCapabilityUsage, new MarkScheme\None());
        yield 'Capability mark type: none, null form input' => [$none, null, null];
        yield 'Capability mark type: none, string form input' => [$none, 'red', null];
        yield 'Capability mark type: none, numeric form input' => [$none, '123', null];

        // Percent
        $percent = new RubricMarkedGrid\CapabilityMark($testCapabilityUsage, new MarkScheme\Percentage());
        yield 'Capability mark type: percent, null form input' => [$percent, null, null];
        yield 'Capability mark type: percent, numeric form input' => [$percent, '50', 2];
        yield 'Capability mark type: percent, percent form input, space' => [$percent, '50 %', 2];
        yield 'Capability mark type: percent, percent form input, no space' => [$percent, '50%', 2];

        // Total mark
        $total = new RubricMarkedGrid\CapabilityMark($testCapabilityUsage, new MarkScheme\TotalMarkFractional(12));
        yield 'Capability mark type: total, null form input' => [$total, null, null];
        yield 'Capability mark type: total, numeric form input' => [$total, '6', 2];
        yield 'Capability mark type: total, fraction form input, space' => [$total, '6 / 12', 2];
        yield 'Capability mark type: total, fraction form input, no space' => [$total, '6/12', 2];

        // Letter grade
        $letter = new RubricMarkedGrid\CapabilityMark($testCapabilityUsage, $this->buildTestLetterGradeMarkScheme());
        yield 'Capability mark type: letter, null form input' => [$letter, null, null];
        yield 'Capability mark type: letter, valid form input' => [$letter, 'A', 3.5];
    }


    /**
     * @dataProvider getTestConvertFormInputToValueData
     * @covers \Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark::convertFormInputToValue
     *
     * @param RubricMarkedGrid\CapabilityMark $capabilityMark
     * @param string|null                     $formInput
     * @param float|null                      $expected
     */
    public function testConvertFormInputToValue(
        RubricMarkedGrid\CapabilityMark $capabilityMark,
        ?string $formInput,
        ?float $expected
    ): void {
        $this->assertSame($expected, $capabilityMark->convertFormInputToValue($formInput));
    }


    /**
     * Data provider for `testConvertFormInputToValueInvalid`
     *
     * @return iterable
     */
    public function getTestConvertFormInputToValueDataInvalid(): iterable
    {
        $testCapabilityUsage = $this->buildTestFormInputConversionCapabilityUsage();

        // Percent
        $percent = new RubricMarkedGrid\CapabilityMark($testCapabilityUsage, new MarkScheme\Percentage());
        yield 'Capability mark type: percent, string' => [$percent, 'red'];

        // Total mark
        $total = new RubricMarkedGrid\CapabilityMark($testCapabilityUsage, new MarkScheme\TotalMarkFractional(12));
        yield 'Capability mark type: total, string' => [$total, 'red'];

        // Letter grade
        $letter = new RubricMarkedGrid\CapabilityMark($testCapabilityUsage, $this->buildTestLetterGradeMarkScheme());
        yield 'Capability mark type: letter, string' => [$letter, 'red'];
    }


    /**
     * @dataProvider getTestConvertFormInputToValueDataInvalid
     * @covers \Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark::convertFormInputToValue
     *
     * @param RubricMarkedGrid\CapabilityMark $capabilityMark
     * @param string|null                     $formInput
     */
    public function testConvertFormInputToValueInvalid(
        RubricMarkedGrid\CapabilityMark $capabilityMark,
        ?string $formInput
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $capabilityMark->convertFormInputToValue($formInput);
    }


    /**
     * Data provider for `testConvertFormInputToValueOutOfRange`
     *
     * @return iterable
     */
    public function getTestConvertFormInputToValueDataOutOfRange(): iterable
    {
        $testCapabilityUsage = $this->buildTestFormInputConversionCapabilityUsage();

        // Percent
        $percent = new RubricMarkedGrid\CapabilityMark($testCapabilityUsage, new MarkScheme\Percentage());
        yield 'Capability mark type: percent, < 0' => [$percent, '-1'];
        yield 'Capability mark type: percent, > 100' => [$percent, '101'];

        // Total mark
        $total = new RubricMarkedGrid\CapabilityMark($testCapabilityUsage, new MarkScheme\TotalMarkFractional(12));
        yield 'Capability mark type: total, number < 0' => [$total, '-1'];
        yield 'Capability mark type: total, number > total' => [$total, '13'];
        yield 'Capability mark type: total, fraction < 0' => [$total, '-1 / 12'];
        yield 'Capability mark type: total, fraction > total' => [$total, '13 / 12'];
    }


    /**
     * @dataProvider getTestConvertFormInputToValueDataOutOfRange
     * @covers \Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark::convertFormInputToValue
     *
     * @param RubricMarkedGrid\CapabilityMark $capabilityMark
     * @param string|null                     $formInput
     */
    public function testConvertFormInputToValueOutOfRange(
        RubricMarkedGrid\CapabilityMark $capabilityMark,
        ?string $formInput
    ): void {
        $this->expectException(RangeException::class);
        $capabilityMark->convertFormInputToValue($formInput);
    }


    /**
     * @return iterable
     */
    public function getConvertFormInputToHumanReadableValueData(): iterable
    {
        $testCapabilityUsage = $this->buildTestFormInputConversionCapabilityUsage();

        // None
        $none = new RubricMarkedGrid\CapabilityMark($testCapabilityUsage, new MarkScheme\None());
        yield 'Capability mark type: none, null form input' => [$none, null, null];
        yield 'Capability mark type: none, string form input' => [$none, 'red', null];
        yield 'Capability mark type: none, numeric form input' => [$none, '123', null];

        // Percent
        $percent = new RubricMarkedGrid\CapabilityMark($testCapabilityUsage, new MarkScheme\Percentage());
        yield 'Capability mark type: percent, null form input' => [$percent, null, null];
        yield 'Capability mark type: percent, numeric form input' => [$percent, '50', '50 %'];
        yield 'Capability mark type: percent, percent form input, space' => [$percent, '50 %', '50 %'];
        yield 'Capability mark type: percent, percent form input, no space' => [$percent, '50%', '50 %'];

        // Total mark
        $total = new RubricMarkedGrid\CapabilityMark($testCapabilityUsage, new MarkScheme\TotalMarkFractional(12));
        yield 'Capability mark type: total, null form input' => [$total, null, null];
        yield 'Capability mark type: total, numeric form input' => [$total, '6', '6 / 12'];
        yield 'Capability mark type: total, fraction form input, space' => [$total, '6 / 12', '6 / 12'];
        yield 'Capability mark type: total, fraction form input, no space' => [$total, '6/12', '6 / 12'];

        // Letter grade
        $letter = new RubricMarkedGrid\CapabilityMark($testCapabilityUsage, $this->buildTestLetterGradeMarkScheme());
        yield 'Capability mark type: letter, null form input' => [$letter, null, null];
        yield 'Capability mark type: letter, valid form input' => [$letter, 'A', 'A'];
    }


    /**
     * @dataProvider getConvertFormInputToHumanReadableValueData
     * @covers \Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark::convertFormInputToHumanReadableValue
     *
     * @param RubricMarkedGrid\CapabilityMark $capabilityMark
     * @param string|null                     $formInput
     * @param string|null                     $expected
     */
    public function testConvertFormInputToHumanReadableValue(
        RubricMarkedGrid\CapabilityMark $capabilityMark,
        ?string $formInput,
        ?string $expected
    ): void {
        $this->assertSame($expected, $capabilityMark->convertFormInputToHumanReadableValue($formInput));
    }


    /**
     * Build a test capability usage for the form input conversion methods.
     *
     * Note that its maximum value is 4.
     *
     * @return CapabilityUsage
     */
    private function buildTestFormInputConversionCapabilityUsage(): CapabilityUsage
    {
        $gridGenerator = new GridGenerator();
        $grid          = $gridGenerator->generateGrid(
            RowSetGenerator::TEST_CAPABILITIES,
            GridGenerator::TEST_LEVELS,
            [
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
                            ],
                        ],
                    ],
                ],
            ]
        );

        return $grid->getCapabilityUsages()[1];
    }


    /**
     * Build a test letter grade mark scheme:
     * * D: 0-25, raw grade 12.5
     * * C: 25-50, raw grade 37.5
     * * B: 50-75, raw grade 62.5
     * * A: 75-100, raw grade 87.5
     *
     * @return MarkScheme\LetterGrade
     */
    private function buildTestLetterGradeMarkScheme(): MarkScheme\LetterGrade
    {
        return new MarkScheme\LetterGrade(
            (new GradeLevel\Group\Model())
                ->setName('Test')
                ->setGradeLevels(
                    new GenericCollection(
                        GradeLevel\Model::class,
                        [
                            (new GradeLevel\Model())
                                ->setCaption('D')
                                ->setLowerBound(0)
                                ->setRawGrade(12.5),
                            (new GradeLevel\Model())
                                ->setCaption('C')
                                ->setLowerBound(25)
                                ->setRawGrade(37.5),
                            (new GradeLevel\Model())
                                ->setCaption('B')
                                ->setLowerBound(50)
                                ->setRawGrade(67.5),
                            (new GradeLevel\Model())
                                ->setCaption('A')
                                ->setLowerBound(75)
                                ->setRawGrade(87.5)
                        ]
                    )
                )
        );
    }
}
