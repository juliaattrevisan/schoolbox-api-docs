<?php
/**
 *
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Rubric\Grid\Test;

use Schoolbox\Assessment\Rubric\Grid\ValidDescriptor;
use \Schoolbox\Assessment\Rubric\Grid\Descriptor;
use PHPUnit\Framework\TestCase;

class ValidDescriptorTest extends TestCase
{
    public function getTestIsSatisfiedByData() : iterable
    {
        $hasMarkRange = true;
        $noMarkRange = false;

        // Always valid
        $descriptorNullPoints = new Descriptor();
        $descriptorZeroPoints = (new Descriptor())->setMaxValue("0");
        $descriptorValid1 = (new Descriptor())->setMaxValue("1");
        $descriptorValid2 = (new Descriptor())->setMaxValue("100");

        /* Invalid only if mark range is on */
        $descriptor1 = (new Descriptor())->setMaxValue("1.5");
        $descriptor2 = (new Descriptor())->setMaxValue("0.75");

        /* Always invalid descriptors */
        $descriptorInvalid1 = (new Descriptor())->setMaxValue("-1");
        $descriptorInvalid2 = (new Descriptor())->setMaxValue("abc");
        $descriptorInvalid3= (new Descriptor())->setMaxValue("-8");
        $descriptorInvalid4 = (new Descriptor())->setMaxValue("-0.5");
        $descriptorInvalid5 = (new Descriptor())->setMaxValue("f00d");
        $descriptorInvalid6 = (new Descriptor())->setMaxValue("l8");
        $descriptorInvalid7 = (new Descriptor())->setMaxValue("1inux");

        // Blank desc points, or desc points of 0, are always fine
        yield [$hasMarkRange, $descriptorNullPoints, true];
        yield [$hasMarkRange, $descriptorZeroPoints, true];
        yield [$noMarkRange, $descriptorNullPoints, true];
        yield [$noMarkRange, $descriptorZeroPoints, true];

        /* Always valid */
        yield [$noMarkRange, $descriptorValid1, true];
        yield [$noMarkRange, $descriptorValid2, true];

        yield [$hasMarkRange, $descriptorValid1, false];
        yield [$hasMarkRange, $descriptorValid2, false];

        /* Invalid only if mark range is on */
        yield [$noMarkRange, $descriptor1, true];
        yield [$noMarkRange, $descriptor2, true];

        yield [$hasMarkRange, $descriptor1, false];
        yield [$hasMarkRange, $descriptor2, false];

        // Always invalid descs
        yield [$noMarkRange, $descriptorInvalid1, false];
        yield [$noMarkRange, $descriptorInvalid2, false];
        yield [$noMarkRange, $descriptorInvalid3, false];
        yield [$noMarkRange, $descriptorInvalid4, false];
        yield [$noMarkRange, $descriptorInvalid5, false];
        yield [$noMarkRange, $descriptorInvalid6, false];
        yield [$noMarkRange, $descriptorInvalid7, false];

        yield [$hasMarkRange, $descriptorInvalid1, false];
        yield [$hasMarkRange, $descriptorInvalid2, false];
        yield [$hasMarkRange, $descriptorInvalid3, false];
        yield [$hasMarkRange, $descriptorInvalid4, false];
        yield [$hasMarkRange, $descriptorInvalid5, false];
        yield [$hasMarkRange, $descriptorInvalid6, false];
        yield [$hasMarkRange, $descriptorInvalid7, false];
    }

    /**
     * @dataProvider getTestIsSatisfiedByData
     * @covers \Schoolbox\Assessment\Rubric\Grid\ValidDescriptor::isSatisfiedBy
     *
     * @param bool $usesMarkRange
     * @param Descriptor $descriptor
     * @param bool $expected                 Whether this test should assert true or false
     */
    public function testIsSatisfiedBy($usesMarkRange, $descriptor, $expected)
    {
        $spec = new ValidDescriptor($usesMarkRange);
        $this->assertSame($expected, $spec->isSatisfiedBy($descriptor));
    }
}
