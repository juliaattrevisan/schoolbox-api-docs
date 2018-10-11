<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
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

        $descriptorNullPoints = new Descriptor();
        $descriptorZeroPoints = (new Descriptor())->setMaxValue("0");

        // Blank desc points, or desc points of 0, are always fine
        yield [$hasMarkRange, $descriptorNullPoints, true];
        yield [$hasMarkRange, $descriptorZeroPoints, true];
        yield [$noMarkRange, $descriptorNullPoints, true];
        yield [$noMarkRange, $descriptorZeroPoints, true];

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
