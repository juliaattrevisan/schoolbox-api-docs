<?php

/**
 *
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Test;

use Schoolbox\Assessment\Rubric\Grid\ValidDescriptor;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Assessment\Rubric\Grid\ValidDescriptor::class)]
class ValidDescriptorTest extends TestCase
{
    public static function getTestIsSatisfiedByData(): iterable
    {
        $hasMarkRange = true;
        $noMarkRange = false;

        // Always valid
        $descriptorNullPoints = '';
        $descriptorZeroPoints = '0';
        $descriptorValid1 = '1';
        $descriptorValid2 = '100';

        /* Invalid only if mark range is on */
        $descriptor1 = '1.5';
        $descriptor2 = '0.75';

        /* Always invalid descriptors */
        $descriptorInvalid1 = '-1';
        $descriptorInvalid2 = 'abc';
        $descriptorInvalid3 = '-8';
        $descriptorInvalid4 = '-0.5';
        $descriptorInvalid5 = 'f00d';
        $descriptorInvalid6 = 'l8';
        $descriptorInvalid7 = '1inux';

        // Blank desc points, or desc points of 0, are always fine
        yield [$hasMarkRange, $descriptorNullPoints, true];
        yield [$hasMarkRange, $descriptorZeroPoints, true];
        yield [$noMarkRange, $descriptorNullPoints, true];
        yield [$noMarkRange, $descriptorZeroPoints, true];

        /* Always valid */
        yield [$noMarkRange, $descriptorValid1, true];
        yield [$noMarkRange, $descriptorValid2, true];

        yield [$hasMarkRange, $descriptorValid1, true];
        yield [$hasMarkRange, $descriptorValid2, true];

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
     *
     * @param bool $usesMarkRange
     * @param string $descriptor
     * @param bool $expected                 Whether this test should assert true or false
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIsSatisfiedByData')]
    public function testIsSatisfiedBy(bool $usesMarkRange, string $descriptor, bool $expected)
    {
        $spec = new ValidDescriptor($usesMarkRange);
        $this->assertSame($expected, $spec->isSatisfiedBy($descriptor));
    }
}
