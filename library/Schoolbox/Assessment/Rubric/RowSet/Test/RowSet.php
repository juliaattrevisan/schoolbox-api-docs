<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\RowSet\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\RowSet\Indicator;
use Schoolbox\Assessment\Rubric\RowSet\Capability;
use Schoolbox\Assessment\Rubric\RowSet\RowSet as RubricRowSet;
use Schoolbox\Resource\Folder\Model as Folder;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Assessment\Rubric\RowSet\RowSet::class)]
class RowSet extends TestCase
{
    /**
     * Data provider for `testRetrieveCapabilityIds`.
     *
     * @return iterable
     */
    public static function getTestRetrieveCapabilityIdsData(): iterable
    {
        $rowSet = new RubricRowSet(new Folder());
        yield 'no capabilities' => [$rowSet, []];

        $rowSet = new RubricRowSet(new Folder());
        $rowSet->getCapabilities()
               ->add(new Capability())
               ->add((new Capability())->setId(3))
               ->add((new Capability())->setId(5))
               ->add((new Capability())->setId(5))
               ->add((new Capability())->setId(6))
               ->add((new Capability())->setId(1));
        yield 'multiple capabilities' => [$rowSet, [1, 3, 5, 6,]];
    }


    /**
     * @param RubricRowSet $rowSet
     * @param array        $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestRetrieveCapabilityIdsData')]
    public function testRetrieveCapabilityIds(RubricRowSet $rowSet, array $expected): void
    {
        $this->assertEquals($expected, $rowSet->retrieveCapabilityIds());
    }


    /**
     * Data provider for `testRetrieveIndicatorIds`.
     *
     * @return iterable
     */
    public static function getTestRetrieveIndicatorIdsData(): iterable
    {
        $rowSet = new RubricRowSet(new Folder());
        yield 'no capabilities' => [$rowSet, []];

        $rowSet = new RubricRowSet(new Folder());
        $rowSet->getCapabilities()
               ->add(new Capability())
               ->add((new Capability())->setId(3))
               ->add((new Capability())->setId(5))
               ->add((new Capability())->setId(5))
               ->add((new Capability())->setId(6))
               ->add((new Capability())->setId(1));
        yield 'multiple capabilities, no criteria' => [$rowSet, []];

        $rowSet = new RubricRowSet(new Folder());

        $capability1 = (new Capability())->setId(3);
        $capability1->getIndicators()
               ->add(new Indicator())
               ->add((new Indicator())->setId(5))
               ->add((new Indicator())->setId(6))
               ->add((new Indicator())->setId(7))
               ->add((new Indicator())->setId(8))
               ->add((new Indicator())->setId(9));
        $rowSet->getCapabilities()
               ->add($capability1);
        yield 'one capability, many criteria' => [$rowSet, [5, 6, 7, 8, 9]];

        $rowSet = new RubricRowSet(new Folder());
        $capability1 = (new Capability())->setId(3);
        $capability1->getIndicators()
               ->add(new Indicator())
               ->add((new Indicator())->setId(5))
               ->add((new Indicator())->setId(6))
               ->add((new Indicator())->setId(7))
               ->add((new Indicator())->setId(8))
               ->add((new Indicator())->setId(9));
        $capability2 = (new Capability())->setId(10);
        $capability2->getIndicators()
               ->add(new Indicator())
               ->add((new Indicator())->setId(5))
               ->add((new Indicator())->setId(16))
               ->add((new Indicator())->setId(17))
               ->add((new Indicator())->setId(18))
               ->add((new Indicator())->setId(19));
        $capability3 = (new Capability())->setId(12);
        $rowSet->getCapabilities()
               ->add($capability1)
               ->add($capability2)
               ->add($capability3);
        yield 'many capabilities, many criteria' => [$rowSet, [5, 6, 7, 8, 9, 16, 17, 18, 19,]];
    }


    /**
     * @param RubricRowSet $rowSet
     * @param array        $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestRetrieveIndicatorIdsData')]
    public function testRetrieveIndicatorIds(RubricRowSet $rowSet, array $expected): void
    {
        $this->assertEquals($expected, $rowSet->retrieveIndicatorIds());
    }
}
