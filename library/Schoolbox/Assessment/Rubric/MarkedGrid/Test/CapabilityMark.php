<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\Grid\IndicatorUsage;
use Schoolbox\Assessment\Rubric\Grid\Test\Generator as GridGenerator;
use Schoolbox\Assessment\Rubric\MarkedGrid as RubricMarkedGrid;
use Schoolbox\Assessment\Rubric\RowSet\Test\Generator as RowSetGenerator;

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
     * Data provider for `testRetrieveHumanReadableMark`.
     *
     * @return iterable
     */
    public function getTestRetrieveHumanReadableMarkData(): iterable
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
        yield 'Capability mark 1 (unscaled)' => [$markedGrid->getCapabilityMarks()[1], '8 / 16'];
        yield 'Capability mark 2 (unscaled)' => [$markedGrid->getCapabilityMarks()[2], '6 / 16'];


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
        yield 'Capability mark 1 (scaled 0.5x)' => [$markedGrid->getCapabilityMarks()[1], '4 / 8'];
        yield 'Capability mark 2 (scaled 2.0x)' => [$markedGrid->getCapabilityMarks()[2], '12 / 32'];


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
        yield 'Capability mark 2 (null capabilities)' => [$markedGrid->getCapabilityMarks()[2], '6 / 16'];
    }


    /**
     * @dataProvider getTestRetrieveHumanReadableMarkData
     * @covers \Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark::retrieveHumanReadableMark
     *
     * @param RubricMarkedGrid\CapabilityMark $capabilityMark
     * @param null|string                     $expected
     */
    public function testRetrieveHumanReadableMark(
        RubricMarkedGrid\CapabilityMark $capabilityMark,
        ?string $expected
    ): void {
        $this->assertSame($expected, $capabilityMark->retrieveHumanReadableMark());
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
}
