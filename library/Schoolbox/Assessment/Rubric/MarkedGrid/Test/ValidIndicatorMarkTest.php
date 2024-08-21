<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid\Test;

use Schoolbox\Assessment\Rubric\Grid\Descriptor;
use Schoolbox\Assessment\Rubric\MarkedGrid\ValidIndicatorMark;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Assessment\Rubric\MarkedGrid\ValidIndicatorMark::class)]
class ValidIndicatorMarkTest extends TestCase
{
    /**
     * Data provider for `testIsSatisfiedBy`.
     *
     * @return iterable
     */
    public static function getTestIsSatisfiedByData(): iterable
    {
        yield 'null descriptor, null value' => [null, null, true];
        yield 'null descriptor, zero value' => [null, 0, false];
        yield 'null descriptor, positive value' => [null, 0.5, false];
        yield 'null descriptor, negative value' => [null, -0.5, false];
        yield 'null descriptor, non-numeric value' => [null, 'qqqqq', false];


        $nullDescriptor = (new Descriptor())
            ->setMinValue(null)
            ->setMaxValue(null);
        yield 'descriptor without value, null value' => [$nullDescriptor, null, true];
        yield 'descriptor without value, zero value' => [$nullDescriptor, 0, false];
        yield 'descriptor without value, positive value' => [$nullDescriptor, 0.5, false];
        yield 'descriptor without value, negative value' => [$nullDescriptor, -0.5, false];
        yield 'descriptor without value, non-numeric value' => [$nullDescriptor, 'qqqqq', false];


        $descriptorWithoutRange = (new Descriptor())
            ->setMinValue(0)
            ->setMaxValue(0);
        yield 'descriptor without range, null value' => [$descriptorWithoutRange, null, false];
        yield 'descriptor without range, equal to allowed value' => [$descriptorWithoutRange, 0, true];
        yield 'descriptor without range, greater than allowed value' => [$descriptorWithoutRange, 1, false];
        yield 'descriptor without range, less than allowed value' => [$descriptorWithoutRange, -1, false];
        yield 'descriptor without range, non-numeric value' => [$descriptorWithoutRange, 'qqqqq', false];


        $fractionalDescriptorWithoutRange = (new Descriptor())
            ->setMinValue(0.5)
            ->setMaxValue(0.5);
        yield 'fractional descriptor without range, equal to allowed value' =>
            [$fractionalDescriptorWithoutRange, 0.5, true];


        $descriptorWithRange = (new Descriptor())
            ->setMinValue(0)
            ->setMaxValue(5);
        yield 'descriptor with range, null value' => [$descriptorWithRange, null, false];
        yield 'descriptor with range, equal to min value' => [$descriptorWithRange, 0, true];
        yield 'descriptor with range, equal to max value' => [$descriptorWithRange, 5, true];
        yield 'descriptor with range, between min and max value' => [$descriptorWithRange, 2, true];
        yield 'descriptor with range, greater than max value' => [$descriptorWithRange, 7, false];
        yield 'descriptor with range, less than min value' => [$descriptorWithRange, -1, false];
        yield 'descriptor with range, non-numeric value' => [$descriptorWithRange, 'qqqqq', false];
        // Fractional values are currently not allowed, even if within range.
        // This may change later.
        yield 'descriptor with range, between min and max value but fractional' => [$descriptorWithRange, 3.5, false];
    }


    /**
     *
     * @param null|Descriptor $descriptor
     * @param float|null      $value
     * @param bool            $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIsSatisfiedByData')]
    public function testIsSatisfiedBy(?Descriptor $descriptor, $value, bool $expected): void
    {
        $spec = new ValidIndicatorMark($descriptor);
        $this->assertSame($expected, $spec->isSatisfiedBy($value));
    }


    /**
     *
     * @param null|Descriptor $descriptor
     * @param float|null      $value
     * @param bool            $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIsSatisfiedByData')]
    public function testIsSatisfiedByStringValues(?Descriptor $descriptor, $value, bool $expected): void
    {
        $spec = new ValidIndicatorMark($descriptor);
        $this->assertSame($expected, $spec->isSatisfiedBy((string)$value));
    }
}
