<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Assessment\Rubric\Grid as RubricGrid;
use Schoolbox\Assessment\Rubric\RowSet;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\MarkScheme;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * @codeCoverageIgnore
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Assessment\Rubric\Grid\Grid::class)]
class Grid extends TestCase
{
    /**
     * Data provider for `testRetrieveGroupUsage`.
     *
     * @return iterable
     */
    public static function getTestRetrieveGroupUsageData(): iterable
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
     *
     * @param RubricGrid\Grid                 $grid
     * @param RowSet\Capability               $group
     * @param null|RubricGrid\CapabilityUsage $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestRetrieveGroupUsageData')]
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
    public static function getTestRetrieveIndicatorUsageData(): iterable
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
     *
     * @param RubricGrid\Grid                $grid
     * @param RowSet\Indicator               $indicator
     * @param null|RubricGrid\IndicatorUsage $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestRetrieveIndicatorUsageData')]
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
    public static function getTestCalculateMaxValueDataFromCapabilities(): iterable
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

        // Max values from descriptor:
        // indicator 1: 2
        // indicator 2: 3
        // indicator 6: 5
        $indicatorUsage1->getDescriptors()[1]                    = (new RubricGrid\Descriptor())->setMaxValue(1);
        $indicatorUsage1->getDescriptors()[2]                    = (new RubricGrid\Descriptor())->setMaxValue(2);
        $indicatorUsage2->getDescriptors()[1]                    = (new RubricGrid\Descriptor())->setMaxValue(null);
        $indicatorUsage2->getDescriptors()[2]                    = (new RubricGrid\Descriptor())->setMaxValue(3);

        $groupUsage2                                             = (new RubricGrid\CapabilityUsage($group2))->setMaxValue(3);
        $indicatorUsage6                                         = new RubricGrid\IndicatorUsage($indicator6);
        $groupUsage2->getIndicatorUsages()[$indicator6->getId()] = $indicatorUsage6;
        $indicatorUsage6->getDescriptors()[1]                    = (new RubricGrid\Descriptor())->setMaxValue(5);
        $indicatorUsage6->getDescriptors()[2]                    = (new RubricGrid\Descriptor())->setMaxValue(5);

        $dueWork1 = (new DueWork())->setCapabilityMarkType(MarkTypeMapper::getInstance()->createModel()->setId(MarkType::TYPE_TOTAL));
        $grid1    = new RubricGrid\Grid($dueWork1, $rowSet);

        yield 'mark per capability, empty grid' => [$grid1, 0];


        $grid2                                          = new RubricGrid\Grid($dueWork1, $rowSet);
        $grid2->getCapabilityUsages()[$group1->getId()] = $groupUsage1;

        yield 'mark per capability, one group usage' => [$grid2, 15];


        $grid3                                          = new RubricGrid\Grid($dueWork1, $rowSet);
        $grid3->getCapabilityUsages()[$group1->getId()] = $groupUsage1;
        $grid3->getCapabilityUsages()[$group2->getId()] = $groupUsage2;

        yield 'mark per capability, two group usages' => [$grid3, 18];


        $duework2 = (new DueWork())->setCapabilityMarkType(MarkTypeMapper::getInstance()->createModel()->setId(MarkType::TYPE_NONE));
        $grid4    = new RubricGrid\Grid($duework2, $rowSet);

        yield 'no mark per capability, empty grid' => [$grid4, 0];


        $grid5                                          = new RubricGrid\Grid($duework2, $rowSet);
        $grid5->getCapabilityUsages()[$group1->getId()] = $groupUsage1;

        yield 'no mark per capability, one group usage' => [$grid5, 5];


        $grid6                                          = new RubricGrid\Grid($duework2, $rowSet);
        $grid6->getCapabilityUsages()[$group1->getId()] = $groupUsage1;
        $grid6->getCapabilityUsages()[$group2->getId()] = $groupUsage2;

        yield 'no mark per capability, two group usages' => [$grid6, 10];
    }


    /**
     * @param RubricGrid\Grid $grid
     * @param float|null      $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestCalculateMaxValueDataFromCapabilities')]
    public function testCalculateMaxValueFromCapabilities(RubricGrid\Grid $grid, ?float $expected): void
    {
        $this->assertSame($expected, $grid->calculateMaxValueFromCapabilities());
    }


    /**
     * Data provider for `testIterateCapabilities`.
     *
     * @return iterable
     */
    public static function getTestIterateCapabilitiesData(): iterable
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
     * @param RubricGrid\Grid $grid
     * @param array           $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIterateCapabilitiesData')]
    public function testIterateCapabilities(RubricGrid\Grid $grid, array $expected): void
    {
        $actual = [];
        foreach ($grid->iterateCapabilities() as $group) {
            $actual[] = $group;
        }
        $this->assertSame($expected, $actual);
    }


    /**
     * Data provider for `testIsCapabilityMarkShown`.
     *
     * @return iterable
     */
    public static function getTestIsCapabilityMarkShownData(): iterable
    {
        $rowSet      = new Rowset\RowSet(new Folder());
        $capability1 = (new RowSet\Capability())->setId(1)->setName('Capability 1');
        $capability2 = (new RowSet\Capability())->setId(2)->setName('Capability 2');
        $indicator1  = (new RowSet\Indicator())->setId(1)->setName('Indicator 1.1');
        $indicator2  = (new RowSet\Indicator())->setId(2)->setName('Indicator 1.2');
        $indicator3  = (new RowSet\Indicator())->setId(3)->setName('Indicator 1.3');
        $indicator4  = (new RowSet\Indicator())->setId(4)->setName('Indicator 2.1');
        $indicator5  = (new RowSet\Indicator())->setId(5)->setName('Indicator 2.1');
        $indicator6  = (new RowSet\Indicator())->setId(6)->setName('Indicator 2.2');

        $capability1->getIndicators()[$indicator1->getId()] = $indicator1;
        $capability1->getIndicators()[$indicator2->getId()] = $indicator2;
        $capability1->getIndicators()[$indicator3->getId()] = $indicator3;

        $capability2->getIndicators()[$indicator4->getId()] = $indicator4;
        $capability2->getIndicators()[$indicator5->getId()] = $indicator5;
        $capability2->getIndicators()[$indicator6->getId()] = $indicator6;

        $rowSet->getCapabilities()[$capability1->getId()] = $capability1;
        $rowSet->getCapabilities()[$capability2->getId()] = $capability2;

        $capabilityUsage1 = (new RubricGrid\CapabilityUsage($capability1))->setMaxValue(0);
        $capabilityUsage2 = (new RubricGrid\CapabilityUsage($capability2))->setMaxValue(1);


        /* Grids now exclusively work on DueWork instances; this former test will die fatally
         * IAssessables are a minimum contract; they shouldn't be expected to require more than that.
         * As per statement to james,
         *
         * "I suppose the cheap answer is that Grid always returns a DueWork instance, instead of an IAssessable,
         * though that comes with its own issues"
         *
         * "I think thats fair assumption"
         */
        //        $grid = new RubricGrid\Grid(new Task(), $rowSet);
        //        $grid->getCapabilityUsages()[$capability2->getId()] = $capabilityUsage2;
        //        yield 'Not a due work' => [$grid, $capability2, false];


        $grid = new RubricGrid\Grid(
            (new DueWork())->setCapabilityMarkType(MarkTypeMapper::getInstance()->createModel()->setId(MarkType::TYPE_NONE)),
            $rowSet
        );
        $grid->getCapabilityUsages()[$capability2->getId()] = $capabilityUsage2;
        yield 'Not mark-per-capability' => [$grid, $capability2, false];


        $grid = new RubricGrid\Grid(
            (new DueWork())->setCapabilityMarkType(MarkTypeMapper::getInstance()->createModel()->setId(MarkType::TYPE_TOTAL)),
            $rowSet
        );
        $grid->getCapabilityUsages()[$capability2->getId()] = $capabilityUsage1;
        yield 'Capability not in grid' => [$grid, $capability1, false];


        $grid = new RubricGrid\Grid(
            (new DueWork())->setCapabilityMarkType(MarkTypeMapper::getInstance()->createModel()->setId(MarkType::TYPE_TOTAL)),
            $rowSet
        );
        $grid->getCapabilityUsages()[$capability1->getId()] = $capabilityUsage1;
        $grid->getCapabilityUsages()[$capability2->getId()] = $capabilityUsage2;
        yield 'Capability with max = 0' => [$grid, $capability1, false];
        yield 'Capability with max > 0' => [$grid, $capability2, true];
    }


    /**
     *
     * @param RubricGrid\Grid   $grid
     * @param RowSet\Capability $capability
     * @param bool              $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIsCapabilityMarkShownData')]
    public function testIsCapabilityMarkShown(
        RubricGrid\Grid $grid,
        RowSet\Capability $capability,
        bool $expected
    ): void {
        $this->assertSame($expected, $grid->isCapabilityMarkShown($capability));
    }


    /**
     * Data provider for `getTestIsCompatibleWithData`.
     * @return iterable
     */
    public static function getTestIsCompatibleWithData(): iterable
    {
        $generator = new Generator();


        // both empty: true
        $grid1 = new RubricGrid\Grid(
            (new DueWork())->setId(1),
            new Rowset\RowSet(new Folder())
        );
        $grid2 = new RubricGrid\Grid(
            (new DueWork())->setId(1),
            new Rowset\RowSet(new Folder())
        );
        yield "empty with empty" => [$grid1, $grid2, true];


        // different assessment: false
        $grid1 = new RubricGrid\Grid(
            (new DueWork())->setId(1),
            new Rowset\RowSet(new Folder())
        );
        $grid2 = new RubricGrid\Grid(
            (new DueWork())->setId(2),
            new Rowset\RowSet(new Folder())
        );
        yield "different assessment" => [$grid1, $grid2, false];


        // both with the same caps, inds, descs: true
        $grid1 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );
        $grid2 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );
        yield "unchanged" => [$grid1, $grid2, true];


        // different points value for a descriptor: false
        $grid1 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );
        $grid2 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );

        $grid1->retrieveIndicatorUsage($grid1->getRowSet()->retrieveIndicator(11))
              ->retrieveDescriptor(1)
              ->setMaxValue(100);
        $grid2->retrieveIndicatorUsage($grid2->getRowSet()->retrieveIndicator(11))
              ->retrieveDescriptor(1)
              ->setMaxValue(101);
        yield "different descriptor value" => [$grid1, $grid2, false];


        // missing descriptor: false
        $grid1 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );
        $grid2 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );

        $indicator = $grid2->getRowSet()->retrieveIndicator(11);
        unset($grid2->retrieveIndicatorUsage($indicator)->getDescriptors()[1]);
        yield "missing descriptor" => [$grid1, $grid2, false];


        // different capabilities: false
        $grid1 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );

        $grid2CapUsages = Generator::TEST_CAPABILITY_USAGES;
        unset($grid2CapUsages[1]);
        $grid2 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            $grid2CapUsages
        );

        yield "removed capability" => [$grid1, $grid2, false];
        yield "added capability"   => [$grid2, $grid1, false];


        // different indicators: false
        $grid1 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );

        $grid2CapUsages = Generator::TEST_CAPABILITY_USAGES;
        unset($grid2CapUsages[1]['indicators'][11]);
        $grid2 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            $grid2CapUsages
        );

        yield "removed indicator" => [$grid1, $grid2, false];
        yield "added indicator"   => [$grid2, $grid1, false];


        // different description for a descriptor: true
        $grid1 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );
        $grid2 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );

        $grid1->retrieveIndicatorUsage($grid1->getRowSet()->retrieveIndicator(11))
              ->retrieveDescriptor(1)
              ->setDescription('y');
        $grid2->retrieveIndicatorUsage($grid2->getRowSet()->retrieveIndicator(11))
              ->retrieveDescriptor(1)
              ->setDescription('z');
        yield "different descriptor description" => [$grid1, $grid2, true];


        // different ordering of descriptors: true (regardless of whether they're in the same level)
        $grid1 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );

        $grid2CapUsages   = Generator::TEST_CAPABILITY_USAGES;
        $grid2Descriptors = &$grid2CapUsages[1]['indicators'][11]['descriptors'];
        $grid2Descriptors[111]['levelId'] = 5;
        $grid2Descriptors[115]['levelId'] = 1;
        $grid2 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            $grid2CapUsages
        );

        yield "different descriptor ordering" => [$grid1, $grid2, true];


        // different max points per capability: true?
        $grid1 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );

        $grid2CapUsages = Generator::TEST_CAPABILITY_USAGES;
        $grid2CapUsages[1]['maxValue'] = 100;
        $grid2 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            $grid2CapUsages
        );

        yield "different max points per capability" => [$grid1, $grid2, false];



        // different mark-per-cap on/off: true
        $grid1 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );
        $grid2 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );
        $grid1->getAssessment()->setCapabilityMarkType(MarkTypeMapper::getInstance()->createModel()->setId(MarkType::TYPE_TOTAL));
        $grid2->getAssessment()->setCapabilityMarkType(MarkTypeMapper::getInstance()->createModel()->setId(MarkType::TYPE_NONE));
        yield "different mark per capability setting" => [$grid1, $grid2, true];


        // different mark range on/off: true
        $grid1 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );
        $grid2 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );
        $grid1->getAssessment()->setUseMarkRange(true);
        $grid2->getAssessment()->setUseMarkRange(false);
        yield "different mark range" => [$grid1, $grid2, true];


        // different total mark: true
        $grid1 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );
        $grid2 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );
        $grid1->getAssessment()->setMarkScheme(new MarkScheme\TotalMarkFractional(100));
        $grid2->getAssessment()->setMarkScheme(new MarkScheme\TotalMarkFractional(200));
        yield "different total mark" => [$grid1, $grid2, true];


        // different mark type: true
        $grid1 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );
        $grid2 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );
        $grid1->getAssessment()->setMarkScheme(new MarkScheme\None());
        $grid2->getAssessment()->setMarkScheme(new MarkScheme\Percentage());
        yield "different mark type" => [$grid1, $grid2, true];
    }


    /**
     *
     * @param RubricGrid\Grid $grid1
     * @param RubricGrid\Grid $grid2
     * @param bool            $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIsCompatibleWithData')]
    public function testIsCompatibleWith(RubricGrid\Grid $grid1, RubricGrid\Grid $grid2, bool $expected): void
    {
        $this->assertSame($expected, $grid1->isCompatibleWith($grid2));
    }
}
