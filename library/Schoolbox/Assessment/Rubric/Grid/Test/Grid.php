<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\Grid as RubricGrid;
use Schoolbox\Assessment\Rubric\RowSet;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * @covers \Schoolbox\Assessment\Rubric\Grid\Grid
 * @codeCoverageIgnore
 */
class Grid extends TestCase
{
    /**
     * Data provider for `testRetrieveGroupUsage`.
     *
     * @return iterable
     */
    public function getTestRetrieveGroupUsageData(): iterable
    {
        // Default rowset
        $rowSet     = new Rowset\RowSet(new Folder());
        $group1     = (new RowSet\Capability())->setId(1)->setName('Group 1');
        $group2     = (new RowSet\Capability())->setId(2)->setName('Group 2');
        $group3     = (new RowSet\Capability())->setId(3)->setName('Group 3');
        $indicator1 = (new RowSet\Indicator())->setId(1)->setName('Indicator 1.1');
        $indicator2 = (new RowSet\Indicator())->setId(2)->setName('Indicator 1.2');
        $indicator3 = (new RowSet\Indicator())->setId(3)->setName('Indicator 1.3');
        $indicator4 = (new RowSet\Indicator())->setId(4)->setName('Indicator 2.1');
        $indicator5 = (new RowSet\Indicator())->setId(5)->setName('Indicator 2.1');
        $indicator6 = (new RowSet\Indicator())->setId(6)->setName('Indicator 2.2');
        $indicator7 = (new RowSet\Indicator())->setId(7)->setName('Indicator 3.3');
        $indicator8 = (new RowSet\Indicator())->setId(8)->setName('Indicator 3.2');
        $indicator9 = (new RowSet\Indicator())->setId(9)->setName('Indicator 3.3');
        $indicator0 = (new RowSet\Indicator())->setId(10)->setName('Indicator 4.1');

        $group1->getIndicators()[$indicator1->getId()] = $indicator1;
        $group1->getIndicators()[$indicator2->getId()] = $indicator2;
        $group1->getIndicators()[$indicator3->getId()] = $indicator3;

        $group2->getIndicators()[$indicator4->getId()] = $indicator4;
        $group2->getIndicators()[$indicator5->getId()] = $indicator5;
        $group2->getIndicators()[$indicator6->getId()] = $indicator6;

        $group3->getIndicators()[$indicator7->getId()] = $indicator7;
        $group3->getIndicators()[$indicator8->getId()] = $indicator8;
        $group3->getIndicators()[$indicator9->getId()] = $indicator9;

        $dueWork = new DueWork();
        $grid    = new RubricGrid\Grid($dueWork, $rowSet);

        // Group and indicator usage
        $groupUsage1                                             = (new RubricGrid\CapabilityUsage($group1))->setMaxValue(15);
        $indicatorUsage1                                         = new RubricGrid\IndicatorUsage($indicator1);
        $indicatorUsage2                                         = new RubricGrid\IndicatorUsage($indicator2);
        $groupUsage1->getIndicatorUsages()[$indicator1->getId()] = $indicatorUsage1;
        $groupUsage1->getIndicatorUsages()[$indicator2->getId()] = $indicatorUsage2;
        $grid->getCapabilityUsages()[$group1->getId()]           = $groupUsage1;

        $groupUsage2                                             = (new RubricGrid\CapabilityUsage($group2))->setMaxValue(15);
        $indicatorUsage6                                         = new RubricGrid\IndicatorUsage($indicator6);
        $groupUsage2->getIndicatorUsages()[$indicator6->getId()] = $indicatorUsage6;
        $grid->getCapabilityUsages()[$group2->getId()]           = $groupUsage2;

        yield 'group 1: in use' => [$grid, $group1, $groupUsage1];
        yield 'group 2: in use' => [$grid, $group2, $groupUsage2];
        yield 'group 3: not in use' => [$grid, $group3, null];
    }


    /**
     * @dataProvider getTestRetrieveGroupUsageData
     * @covers       \Schoolbox\Assessment\Rubric\Grid\Grid::retrieveCapabilityUsage
     *
     * @param RubricGrid\Grid                 $grid
     * @param RowSet\Capability               $group
     * @param null|RubricGrid\CapabilityUsage $expected
     */
    public function testRetrieveGroupUsage(
        RubricGrid\Grid $grid,
        RowSet\Capability $group,
        ?RubricGrid\CapabilityUsage $expected
    ): void {
        $this->assertSame($expected, $grid->retrieveCapabilityUsage($group));
    }


    /**
     * Data provider for `testRetrieveIndicatorUsage`.
     *
     * @return iterable
     */
    public function getTestRetrieveIndicatorUsageData(): iterable
    {
        // Default rowset
        $rowSet     = new Rowset\RowSet(new Folder());
        $group1     = (new RowSet\Capability())->setId(1)->setName('Group 1');
        $group2     = (new RowSet\Capability())->setId(2)->setName('Group 2');
        $group3     = (new RowSet\Capability())->setId(3)->setName('Group 3');
        $indicator1 = (new RowSet\Indicator())->setId(1)->setName('Indicator 1.1');
        $indicator2 = (new RowSet\Indicator())->setId(2)->setName('Indicator 1.2');
        $indicator3 = (new RowSet\Indicator())->setId(3)->setName('Indicator 1.3');
        $indicator4 = (new RowSet\Indicator())->setId(4)->setName('Indicator 2.1');
        $indicator5 = (new RowSet\Indicator())->setId(5)->setName('Indicator 2.1');
        $indicator6 = (new RowSet\Indicator())->setId(6)->setName('Indicator 2.2');
        $indicator7 = (new RowSet\Indicator())->setId(7)->setName('Indicator 3.3');
        $indicator8 = (new RowSet\Indicator())->setId(8)->setName('Indicator 3.2');
        $indicator9 = (new RowSet\Indicator())->setId(9)->setName('Indicator 3.3');
        $indicator0 = (new RowSet\Indicator())->setId(10)->setName('Indicator 4.1');

        $group1->getIndicators()[$indicator1->getId()] = $indicator1;
        $group1->getIndicators()[$indicator2->getId()] = $indicator2;
        $group1->getIndicators()[$indicator3->getId()] = $indicator3;

        $group2->getIndicators()[$indicator4->getId()] = $indicator4;
        $group2->getIndicators()[$indicator5->getId()] = $indicator5;
        $group2->getIndicators()[$indicator6->getId()] = $indicator6;

        $group3->getIndicators()[$indicator7->getId()] = $indicator7;
        $group3->getIndicators()[$indicator8->getId()] = $indicator8;
        $group3->getIndicators()[$indicator9->getId()] = $indicator9;

        $dueWork = new DueWork();
        $grid    = new RubricGrid\Grid($dueWork, $rowSet);

        // Group and indicator usage
        $groupUsage1                                             = (new RubricGrid\CapabilityUsage($group1))->setMaxValue(15);
        $indicatorUsage1                                         = new RubricGrid\IndicatorUsage($indicator1);
        $indicatorUsage2                                         = new RubricGrid\IndicatorUsage($indicator2);
        $groupUsage1->getIndicatorUsages()[$indicator1->getId()] = $indicatorUsage1;
        $groupUsage1->getIndicatorUsages()[$indicator2->getId()] = $indicatorUsage2;
        $grid->getCapabilityUsages()[$group1->getId()]           = $groupUsage1;

        $groupUsage2                                             = (new RubricGrid\CapabilityUsage($group2))->setMaxValue(15);
        $indicatorUsage6                                         = new RubricGrid\IndicatorUsage($indicator6);
        $groupUsage2->getIndicatorUsages()[$indicator6->getId()] = $indicatorUsage6;
        $grid->getCapabilityUsages()[$group2->getId()]           = $groupUsage2;

        yield 'indicator 1.1: in use'     => [$grid, $indicator1, $indicatorUsage1];
        yield 'indicator 1.2: in use'     => [$grid, $indicator2, $indicatorUsage2];
        yield 'indicator 1.3: not in use' => [$grid, $indicator3, null];
        yield 'indicator 2.1: not in use' => [$grid, $indicator4, null];
        yield 'indicator 2.2: not in use' => [$grid, $indicator5, null];
        yield 'indicator 2.3: in use'     => [$grid, $indicator6, $indicatorUsage6];
        yield 'indicator 3.1: not in use' => [$grid, $indicator7, null];
        yield 'indicator 3.2: not in use' => [$grid, $indicator8, null];
        yield 'indicator 3.3: not in use' => [$grid, $indicator9, null];
        yield 'indicator 4.1: not in use' => [$grid, $indicator0, null];
    }


    /**
     * @dataProvider getTestRetrieveIndicatorUsageData
     * @covers       \Schoolbox\Assessment\Rubric\Grid\Grid::retrieveIndicatorUsage
     *
     * @param RubricGrid\Grid                $grid
     * @param RowSet\Indicator               $indicator
     * @param null|RubricGrid\IndicatorUsage $expected
     */
    public function testRetrieveIndicatorUsage(
        RubricGrid\Grid $grid,
        RowSet\Indicator $indicator,
        ?RubricGrid\IndicatorUsage $expected
    ): void {
        $this->assertSame($expected, $grid->retrieveIndicatorUsage($indicator));
    }


    /**
     * Data provider for `testCalculateMaxValueFromCapabilities`
     *
     * @return iterable
     */
    public function getTestCalculateMaxValueDataFromCapabilities(): iterable
    {
        $rowSet     = new Rowset\RowSet(new Folder());
        $group1     = (new RowSet\Capability())->setId(1)->setName('Group 1');
        $group2     = (new RowSet\Capability())->setId(2)->setName('Group 2');
        $group3     = (new RowSet\Capability())->setId(3)->setName('Group 3');
        $indicator1 = (new RowSet\Indicator())->setId(1)->setName('Indicator 1.1');
        $indicator2 = (new RowSet\Indicator())->setId(2)->setName('Indicator 1.2');
        $indicator3 = (new RowSet\Indicator())->setId(3)->setName('Indicator 1.3');
        $indicator4 = (new RowSet\Indicator())->setId(4)->setName('Indicator 2.1');
        $indicator5 = (new RowSet\Indicator())->setId(5)->setName('Indicator 2.1');
        $indicator6 = (new RowSet\Indicator())->setId(6)->setName('Indicator 2.2');
        $indicator7 = (new RowSet\Indicator())->setId(7)->setName('Indicator 3.3');
        $indicator8 = (new RowSet\Indicator())->setId(8)->setName('Indicator 3.2');
        $indicator9 = (new RowSet\Indicator())->setId(9)->setName('Indicator 3.3');

        $group1->getIndicators()[$indicator1->getId()] = $indicator1;
        $group1->getIndicators()[$indicator2->getId()] = $indicator2;
        $group1->getIndicators()[$indicator3->getId()] = $indicator3;

        $group2->getIndicators()[$indicator4->getId()] = $indicator4;
        $group2->getIndicators()[$indicator5->getId()] = $indicator5;
        $group2->getIndicators()[$indicator6->getId()] = $indicator6;

        $group3->getIndicators()[$indicator7->getId()] = $indicator7;
        $group3->getIndicators()[$indicator8->getId()] = $indicator8;
        $group3->getIndicators()[$indicator9->getId()] = $indicator9;

        $groupUsage1                                             = (new RubricGrid\CapabilityUsage($group1))->setMaxValue(15);
        $indicatorUsage1                                         = new RubricGrid\IndicatorUsage($indicator1);
        $indicatorUsage2                                         = new RubricGrid\IndicatorUsage($indicator2);
        $groupUsage1->getIndicatorUsages()[$indicator1->getId()] = $indicatorUsage1;
        $groupUsage1->getIndicatorUsages()[$indicator2->getId()] = $indicatorUsage2;

        $groupUsage2                                             = (new RubricGrid\CapabilityUsage($group2))->setMaxValue(3);
        $indicatorUsage6                                         = new RubricGrid\IndicatorUsage($indicator6);
        $groupUsage2->getIndicatorUsages()[$indicator6->getId()] = $indicatorUsage6;

        $dueWork = new DueWork();
        $grid1   = new RubricGrid\Grid($dueWork, $rowSet);

        yield 'empty grid' => [$grid1, 0];


        // Add descriptors
        $grid2                                          = new RubricGrid\Grid($dueWork, $rowSet);
        $grid2->getCapabilityUsages()[$group1->getId()] = $groupUsage1;

        yield 'one group usage' => [$grid2, 15];


        $grid3                                          = new RubricGrid\Grid($dueWork, $rowSet);
        $grid3->getCapabilityUsages()[$group1->getId()] = $groupUsage1;
        $grid3->getCapabilityUsages()[$group2->getId()] = $groupUsage2;

        yield 'two group usages' => [$grid3, 18];
    }


    /**
     * @dataProvider getTestCalculateMaxValueDataFromCapabilities
     * @covers       \Schoolbox\Assessment\Rubric\Grid\Grid::calculateMaxValueFromCapabilities
     *
     * @param RubricGrid\Grid $grid
     * @param float|null      $expected
     */
    public function testCalculateMaxValueFromCapabilities(RubricGrid\Grid $grid, ?float $expected): void
    {
        $this->assertSame($expected, $grid->calculateMaxValueFromCapabilities());
    }


    /**
     * Data provider for `testIterateCapabilities`.
     *
     * @return iterable
     */
    public function getTestIterateCapabilitiesData(): iterable
    {
        // rowset has three capabilities
        // try grids with: none, one, all


        $rowSet     = new Rowset\RowSet(new Folder());
        $group1     = (new RowSet\Capability())->setId(1)->setName('Group 1');
        $group2     = (new RowSet\Capability())->setId(2)->setName('Group 2');
        $group3     = (new RowSet\Capability())->setId(3)->setName('Group 3');
        $indicator1 = (new RowSet\Indicator())->setId(1)->setName('Indicator 1.1');
        $indicator2 = (new RowSet\Indicator())->setId(2)->setName('Indicator 1.2');
        $indicator3 = (new RowSet\Indicator())->setId(3)->setName('Indicator 1.3');
        $indicator4 = (new RowSet\Indicator())->setId(4)->setName('Indicator 2.1');
        $indicator5 = (new RowSet\Indicator())->setId(5)->setName('Indicator 2.1');
        $indicator6 = (new RowSet\Indicator())->setId(6)->setName('Indicator 2.2');
        $indicator7 = (new RowSet\Indicator())->setId(7)->setName('Indicator 3.3');
        $indicator8 = (new RowSet\Indicator())->setId(8)->setName('Indicator 3.2');
        $indicator9 = (new RowSet\Indicator())->setId(9)->setName('Indicator 3.3');

        $group1->getIndicators()[$indicator1->getId()] = $indicator1;
        $group1->getIndicators()[$indicator2->getId()] = $indicator2;
        $group1->getIndicators()[$indicator3->getId()] = $indicator3;

        $group2->getIndicators()[$indicator4->getId()] = $indicator4;
        $group2->getIndicators()[$indicator5->getId()] = $indicator5;
        $group2->getIndicators()[$indicator6->getId()] = $indicator6;

        $group3->getIndicators()[$indicator7->getId()] = $indicator7;
        $group3->getIndicators()[$indicator8->getId()] = $indicator8;
        $group3->getIndicators()[$indicator9->getId()] = $indicator9;

        $rowSet->getCapabilities()[$group1->getId()] = $group1;
        $rowSet->getCapabilities()[$group2->getId()] = $group2;
        $rowSet->getCapabilities()[$group3->getId()] = $group3;


        $groupUsage1 = new RubricGrid\CapabilityUsage($group1);
        $groupUsage2 = new RubricGrid\CapabilityUsage($group2);
        $groupUsage3 = new RubricGrid\CapabilityUsage($group3);

        $dueWork = new DueWork();
        $grid1   = new RubricGrid\Grid($dueWork, $rowSet);

        yield 'No capabilities used' => [$grid1, [$group1, $group2, $group3]];


        $grid2                                          = new RubricGrid\Grid($dueWork, $rowSet);
        $grid2->getCapabilityUsages()[$group2->getId()] = $groupUsage2;

        yield 'Group 2 used' => [$grid2, [$group2, $group1, $group3]];


        $grid3                                          = new RubricGrid\Grid($dueWork, $rowSet);
        $grid3->getCapabilityUsages()[$group3->getId()] = $groupUsage3;
        $grid3->getCapabilityUsages()[$group2->getId()] = $groupUsage2;


        yield 'Group 3, 2 used' => [$grid3, [$group3, $group2, $group1]];


        $grid4                                          = new RubricGrid\Grid($dueWork, $rowSet);
        $grid4->getCapabilityUsages()[$group1->getId()] = $groupUsage1;
        $grid4->getCapabilityUsages()[$group3->getId()] = $groupUsage3;
        $grid4->getCapabilityUsages()[$group2->getId()] = $groupUsage2;

        yield 'Group 1, 3, 2 used' => [$grid4, [$group1, $group3, $group2]];


        $grid5                                          = new RubricGrid\Grid($dueWork, $rowSet);
        $grid5->getCapabilityUsages()[$group3->getId()] = $groupUsage3;
        $grid5->getCapabilityUsages()[$group2->getId()] = $groupUsage2;
        $grid5->getCapabilityUsages()[$group1->getId()] = $groupUsage1;

        yield 'Group 3, 2, 1 used' => [$grid5, [$group3, $group2, $group1]];
    }


    /**
     * @dataProvider getTestIterateCapabilitiesData
     * @covers \Schoolbox\Assessment\Rubric\Grid\Grid::iterateCapabilities
     *
     * @param RubricGrid\Grid $grid
     * @param array           $expected
     */
    public function testIterateCapabilities(RubricGrid\Grid $grid, array $expected): void
    {
        $actual = [];
        foreach ($grid->iterateCapabilities() as $group) {
            $actual[] = $group;
        }
        $this->assertSame($expected, $actual);
    }


    /**
     * Data provider for `testCalculateMarkMultiplier`.
     *
     * @return iterable
     */
    public function getTestCalculateMarkMultiplierData(): iterable
    {
        // Build the row set
        $folder = new Folder();
        $rowSet = new Rowset\RowSet($folder);

        // Build caps and indicators
        $capability1 = (new RowSet\Capability())->setId(1)->setName('Capability 1');
        $capability2 = (new RowSet\Capability())->setId(2)->setName('Capability 2');
        $indicator1  = (new RowSet\Indicator())->setId(1)->setName('Indicator 1.1');
        $indicator2  = (new RowSet\Indicator())->setId(2)->setName('Indicator 1.2');
        $indicator3  = (new RowSet\Indicator())->setId(3)->setName('Indicator 2.1');
        $indicator4  = (new RowSet\Indicator())->setId(4)->setName('Indicator 2.2');

        $capability1->getIndicators()[$indicator1->getId()] = $indicator1;
        $capability1->getIndicators()[$indicator2->getId()] = $indicator2;

        $capability2->getIndicators()[$indicator3->getId()] = $indicator3;
        $capability2->getIndicators()[$indicator4->getId()] = $indicator4;


        // Build grids
        $dueWork = new DueWork();
        $levels  = new GenericCollection(
            RubricGrid\Level::class,
            [
                1 => (new RubricGrid\Level())->setId(1)->setName('0'),
                2 => (new RubricGrid\Level())->setId(2)->setName('2'),
                3 => (new RubricGrid\Level())->setId(3)->setName('4'),
                4 => (new RubricGrid\Level())->setId(4)->setName('6'),
                5 => (new RubricGrid\Level())->setId(5)->setName('8'),
            ]
        );


        // Build capability and indicator usage
        $capabilityUsage1 = (new RubricGrid\CapabilityUsage($capability1))->setMaxValue(8);
        $capabilityUsage2 = (new RubricGrid\CapabilityUsage($capability2))->setMaxValue(4);
        $indicatorUsage1  = new RubricGrid\IndicatorUsage($indicator1);
        $indicatorUsage2  = new RubricGrid\IndicatorUsage($indicator2);
        $indicatorUsage3  = new RubricGrid\IndicatorUsage($indicator3);
        $indicatorUsage4  = new RubricGrid\IndicatorUsage($indicator4);

        // Build descriptors, add to indicators
        $descriptorValue = 0;
        foreach ($levels as $levelId => $level) {
            $descriptor = (new RubricGrid\Descriptor())
                ->setId($levelId)
                ->setDescription((string)$descriptorValue)
                ->setMinValue((float)$descriptorValue)
                ->setMaxValue((float)$descriptorValue);
            $indicatorUsage1->getDescriptors()[$levelId] = $descriptor;
            $indicatorUsage2->getDescriptors()[$levelId] = $descriptor;
            $indicatorUsage3->getDescriptors()[$levelId] = $descriptor;
            $indicatorUsage4->getDescriptors()[$levelId] = $descriptor;

            $descriptorValue += 2;
        }

        $capabilityUsage1->getIndicatorUsages()[$indicator1->getId()] = $indicatorUsage1;
        $capabilityUsage1->getIndicatorUsages()[$indicator2->getId()] = $indicatorUsage2;

        $capabilityUsage2->getIndicatorUsages()[$indicator3->getId()] = $indicatorUsage3;
        $capabilityUsage2->getIndicatorUsages()[$indicator4->getId()] = $indicatorUsage4;


        // Grid 1: no capability usage
        $grid1 = new RubricGrid\Grid($dueWork, $rowSet);
        $grid1->setLevels($levels);
        yield 'No capabilities' => [$grid1, 0];


        // Grid 2: indicator max for capabilities = capability max
        $grid2 = new RubricGrid\Grid($dueWork, $rowSet);
        $grid2->setLevels($levels);

//        $capabilityUsage1 = (new RubricGrid\CapabilityUsage($capability1))->setMaxValue(16);
//        $capabilityUsage2 = (new RubricGrid\CapabilityUsage($capability2))->setMaxValue(16);

        $grid2->getCapabilityUsages()[$capability1->getId()] = $capabilityUsage1->setMaxValue(16);
        $grid2->getCapabilityUsages()[$capability2->getId()] = $capabilityUsage2->setMaxValue(16);

        yield 'Max capability mark, indicator max = capability max' => [$grid2, 1];


        // Grid 3: indicator max for capabilities > capability max
        $grid3 = new RubricGrid\Grid($dueWork, $rowSet);
        $grid3->setLevels($levels);

//        unset($capabilityUsage1, $capabilityUsage2);
//        $capabilityUsage1 = (new RubricGrid\CapabilityUsage($capability1))->setMaxValue(8);
//        $capabilityUsage2 = (new RubricGrid\CapabilityUsage($capability2))->setMaxValue(32);

        $grid3->getCapabilityUsages()[$capability1->getId()] = $capabilityUsage1->setMaxValue(8);;
        $grid3->getCapabilityUsages()[$capability2->getId()] = $capabilityUsage2->setMaxValue(32);;

        yield 'Max capability mark, 1.25x indicator max = capability max' => [$grid3, 1.25];


        // Grid 4: indicator max for capabilities < capability max
        $grid4 = new RubricGrid\Grid($dueWork, $rowSet);
        $grid4->setLevels($levels);

//        unset($capabilityUsage1, $capabilityUsage2);
//        $capabilityUsage1 = (new RubricGrid\CapabilityUsage($capability1))->setMaxValue(1);
//        $capabilityUsage2 = (new RubricGrid\CapabilityUsage($capability2))->setMaxValue(3);

        $grid4->getCapabilityUsages()[$capability1->getId()] = $capabilityUsage1->setMaxValue(1);
        $grid4->getCapabilityUsages()[$capability2->getId()] = $capabilityUsage2->setMaxValue(3);

        yield 'Max capability mark, 0.125x indicator max = capability max' => [$grid4, 0.125];
    }


    /**
     * @dataProvider getTestCalculateMarkMultiplierData
     * @covers       \Schoolbox\Assessment\Rubric\Grid\Grid::calculateMarkMultiplier
     *
     * @param RubricGrid\Grid $grid
     * @param float           $expected
     */
    public function testCalculateMarkMultiplier(RubricGrid\Grid $grid, float $expected): void
    {
        $this->assertSame($expected, $grid->calculateMarkMultiplier());
    }
}
