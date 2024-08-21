<?php

/**
 *
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Test;

use Schoolbox\Assessment\Rubric\Grid\ValidCapabilityMax;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Assessment\Rubric\Grid\ValidCapabilityMax::class)]
class ValidCapabilityMaxTest extends TestCase
{
    public static function getTestIsSatisfiedByData(): iterable
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
        $capabilityMax8 = "0";

        /* Always invalid cap maximums */
        $capabilityMaxInvalid1 = "abc";
        $capabilityMaxInvalid2 = "-1";
        $capabilityMaxInvalid3 = "-8";
        $capabilityMaxInvalid4 = "-0.5";
        $capabilityMaxInvalid5 = "f00d";
        $capabilityMaxInvalid6 = "l8";
        $capabilityMaxInvalid7 = "1inux";

        yield [$hasMarkPerCap, $capabilityMax1, true];
        yield [$hasMarkPerCap, $capabilityMax2, true];
        yield [$hasMarkPerCap, $capabilityMax3, true];
        yield [$hasMarkPerCap, $capabilityMax4, true];
        yield [$hasMarkPerCap, $capabilityMax5, true];
        yield [$hasMarkPerCap, $capabilityMax6, true];
        yield [$hasMarkPerCap, $capabilityMax7, true];
        yield [$hasMarkPerCap, $capabilityMax8, true];
        yield [$hasMarkPerCap, $capabilityMaxInvalid1, false];
        yield [$hasMarkPerCap, $capabilityMaxInvalid2, false];
        yield [$hasMarkPerCap, $capabilityMaxInvalid3, false];
        yield [$hasMarkPerCap, $capabilityMaxInvalid4, false];
        yield [$hasMarkPerCap, $capabilityMaxInvalid5, false];
        yield [$hasMarkPerCap, $capabilityMaxInvalid6, false];
        yield [$hasMarkPerCap, $capabilityMaxInvalid7, false];

        // If mark per cap is off, we consider the input to always be valid
        yield [$noMarkPerCap, $capabilityMax1, true];
        yield [$noMarkPerCap, $capabilityMax2, true];
        yield [$noMarkPerCap, $capabilityMax3, true];
        yield [$noMarkPerCap, $capabilityMax4, true];
        yield [$noMarkPerCap, $capabilityMax5, true];
        yield [$noMarkPerCap, $capabilityMax6, true];
        yield [$noMarkPerCap, $capabilityMax7, true];
        yield [$noMarkPerCap, $capabilityMax8, true];
        yield [$noMarkPerCap, $capabilityMaxInvalid1, true];
        yield [$noMarkPerCap, $capabilityMaxInvalid2, true];
        yield [$noMarkPerCap, $capabilityMaxInvalid3, true];
        yield [$noMarkPerCap, $capabilityMaxInvalid4, true];
        yield [$noMarkPerCap, $capabilityMaxInvalid5, true];
        yield [$noMarkPerCap, $capabilityMaxInvalid6, true];
        yield [$noMarkPerCap, $capabilityMaxInvalid7, true];
    }

    /**
     *
     * @param bool $markPerCap
     * @param string $capabilityMax
     * @param bool $expected                 Whether this test should assert true or false
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIsSatisfiedByData')]
    public function testIsSatisfiedBy(bool $markPerCap, string $capabilityMax, bool $expected)
    {
        $spec = new ValidCapabilityMax($markPerCap);
        $this->assertSame($expected, $spec->isSatisfiedBy($capabilityMax));
    }
}
