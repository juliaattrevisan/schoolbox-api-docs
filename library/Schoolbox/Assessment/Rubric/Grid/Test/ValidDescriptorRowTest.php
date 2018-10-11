<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Rubric\Grid\Test;

use Schoolbox\Assessment\Rubric\Grid\ValidDescriptorRow;
use \Schoolbox\Collection;
use PHPUnit\Framework\TestCase;

class ValidDescriptorRowTest extends TestCase
{
    public function getTestIsSatisfiedByData() : iterable
    {
        $hasMarkRange = true;
        $noMarkRange = false;

        $descriptor1 = (new Descriptor())->setMaxValue("1");
        $descriptor2 = (new Descriptor())->setMaxValue("2");
        $descriptor3= (new Descriptor())->setMaxValue("3");
        $descriptor4 = (new Descriptor())->setMaxValue("4");
        $descriptor5 = (new Descriptor())->setMaxValue("5");
        $descriptor6 = (new Descriptor())->setMaxValue("6");
        $descriptor7 = (new Descriptor())->setMaxValue("7");

        $descriptorRowMonotonic = new Collection();
        $descriptorRowMonotonic->add($descriptor1);
        $descriptorRowMonotonic->add($descriptor3);
        $descriptorRowMonotonic->add($descriptor5);

        $descriptorRowNonMonotonic = new Collection();
        $descriptorRowNonMonotonic->add($descriptor7);
        $descriptorRowNonMonotonic->add($descriptor3);
        $descriptorRowNonMonotonic->add($descriptor5);
        $descriptorRowNonMonotonic->add($descriptor6);

        yield [$hasMarkRange, $descriptorRowMonotonic, true];
        yield [$noMarkRange, $descriptorRowMonotonic, true];

        yield [$hasMarkRange, $descriptorRowNonMonotonic, false];
        yield [$noMarkRange, $descriptorRowNonMonotonic, true];
    }

    /**
     * @dataProvider getTestIsSatisfiedByData
     * @covers \Schoolbox\Assessment\Rubric\Grid\ValidDescriptorRow::isSatisfiedBy
     *
     * @param bool $usesMarkRange
     * @param Collection $descriptorRow
     * @param bool $expected                 Whether this test should assert true or false
     */
    public function testIsSatisfiedBy($usesMarkRange, $descriptorRow, $expected)
    {
        $spec = new ValidDescriptorRow($usesMarkRange);
        $this->assertSame($expected, $spec->isSatisfiedBy($descriptorRow));
    }
}
