<?php
/**
 *
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Rubric\Grid\Test;

use Schoolbox\Assessment\Rubric\Grid\ValidCapabilityMax;
use PHPUnit\Framework\TestCase;

class ValidCapabilityMaxTest extends TestCase
{
    public function getTestIsSatisfiedByData() : iterable
    {
        $hasMarkPerCap = true;
        $noMarkPerCap = false;

        /* Valid cap maximums */
        $capabilityMax1 = "5";
        $capabilityMax2 = "8.6";
        $capabilityMax3 = "16";
        $capabilityMax4 = "1";
        $capabilityMax5 = "100";
        $capabilityMax6 = "55";
        $capabilityMax7 = "0.75";

        /* Always invalid cap maximums */
        $capabilityMaxInvalid1 = "0";
        $capabilityMaxInvalid2 = "abc";
        $capabilityMaxInvalid3 = "-1";
        $capabilityMaxInvalid4 = "-8";
        $capabilityMaxInvalid5 = "-0.5";
        $capabilityMaxInvalid6 = "f00d";
        $capabilityMaxInvalid7 = "l8";
        $capabilityMaxInvalid8 = "1inux";

        yield [$hasMarkPerCap, $capabilityMax1, true];
        yield [$hasMarkPerCap, $capabilityMax2, true];
        yield [$hasMarkPerCap, $capabilityMax3, true];
        yield [$hasMarkPerCap, $capabilityMax4, true];
        yield [$hasMarkPerCap, $capabilityMax5, true];
        yield [$hasMarkPerCap, $capabilityMax6, true];
        yield [$hasMarkPerCap, $capabilityMax7, true];
        yield [$hasMarkPerCap, $capabilityMaxInvalid1, false];
        yield [$hasMarkPerCap, $capabilityMaxInvalid2, false];
        yield [$hasMarkPerCap, $capabilityMaxInvalid3, false];
        yield [$hasMarkPerCap, $capabilityMaxInvalid4, false];
        yield [$hasMarkPerCap, $capabilityMaxInvalid5, false];
        yield [$hasMarkPerCap, $capabilityMaxInvalid6, false];
        yield [$hasMarkPerCap, $capabilityMaxInvalid7, false];
        yield [$hasMarkPerCap, $capabilityMaxInvalid8, false];

        // If mark per cap is off, we consider the input to always be valid
        yield [$noMarkPerCap, $capabilityMax1, true];
        yield [$noMarkPerCap, $capabilityMax2, true];
        yield [$noMarkPerCap, $capabilityMax3, true];
        yield [$noMarkPerCap, $capabilityMax4, true];
        yield [$noMarkPerCap, $capabilityMax5, true];
        yield [$noMarkPerCap, $capabilityMax6, true];
        yield [$noMarkPerCap, $capabilityMax7, true];
        yield [$noMarkPerCap, $capabilityMaxInvalid1, true];
        yield [$noMarkPerCap, $capabilityMaxInvalid2, true];
        yield [$noMarkPerCap, $capabilityMaxInvalid3, true];
        yield [$noMarkPerCap, $capabilityMaxInvalid4, true];
        yield [$noMarkPerCap, $capabilityMaxInvalid5, true];
        yield [$noMarkPerCap, $capabilityMaxInvalid6, true];
        yield [$noMarkPerCap, $capabilityMaxInvalid7, true];
        yield [$noMarkPerCap, $capabilityMaxInvalid8, true];
    }

    /**
     * @dataProvider getTestIsSatisfiedByData
     * @covers \Schoolbox\Assessment\Rubric\Grid\ValidCapabilityMax::isSatisfiedBy
     *
     * @param bool $markPerCap
     * @param string $capabilityMax
     * @param bool $expected                 Whether this test should assert true or false
     */
    public function testIsSatisfiedBy(bool $markPerCap, string $capabilityMax, bool $expected)
    {
        $spec = new ValidCapabilityMax($markPerCap);
        $this->assertSame($expected, $spec->isSatisfiedBy($capabilityMax));
    }
}
