<?php

/**
 *
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Test;

use Schoolbox\Assessment\Rubric\Grid\ValidMonotonicDescriptorRow;
use Schoolbox\Assessment\Rubric\Grid\Descriptor;
use Schoolbox\Collection;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Assessment\Rubric\Grid\ValidMonotonicDescriptorRow::class)]
class ValidMonotonicDescriptorRowTest extends TestCase
{
    public static function getTestIsSatisfiedByData(): iterable
    {
        $hasMarkRange = true;
        $noMarkRange = false;

        $descriptorNullPoints = new Descriptor();

        // We don't have to worry about nonsensical desc values in here, cause ValidDescriptor already caught it
        $descriptor0 = (new Descriptor())->setMaxValue(0.0);
        $descriptor1 = (new Descriptor())->setMaxValue(1.0);
        $descriptor2point75 = (new Descriptor())->setMaxValue(2.75);
        $descriptor3 = (new Descriptor())->setMaxValue(3.0);
        $descriptor4point5 = (new Descriptor())->setMaxValue(4.5);
        $descriptor5 = (new Descriptor())->setMaxValue(5.0);
        $descriptor6 = (new Descriptor())->setMaxValue(6.0);
        $descriptor7 = (new Descriptor())->setMaxValue(7.0);

        /* Monotonic descriptor rows */
        $descriptorRowMonotonic1 = new Collection();
        $descriptorRowMonotonic1->add($descriptor1);
        $descriptorRowMonotonic1->add($descriptor3);
        $descriptorRowMonotonic1->add($descriptor5);

        $descriptorRowMonotonic2 = new Collection();
        $descriptorRowMonotonic2->add($descriptor7);
        $descriptorRowMonotonic2->add($descriptor5);
        $descriptorRowMonotonic2->add($descriptor2point75);

        $descriptorRowMonotonic3 = new Collection();
        $descriptorRowMonotonic3->add($descriptorNullPoints);
        $descriptorRowMonotonic3->add($descriptor0);
        $descriptorRowMonotonic3->add($descriptor3);
        $descriptorRowMonotonic3->add($descriptor6);
        $descriptorRowMonotonic3->add($descriptorNullPoints);

        $descriptorRowMonotonic4 = new Collection();
        $descriptorRowMonotonic4->add($descriptor5);
        $descriptorRowMonotonic4->add($descriptorNullPoints);
        $descriptorRowMonotonic4->add($descriptor4point5);
        $descriptorRowMonotonic4->add($descriptor0);

        $descriptorRowMonotonic5 = new Collection();
        $descriptorRowMonotonic5->add($descriptor0);
        $descriptorRowMonotonic5->add($descriptorNullPoints);
        $descriptorRowMonotonic5->add($descriptor5);
        $descriptorRowMonotonic5->add($descriptor6);

        $descriptorRowMonotonic6 = new Collection();
        $descriptorRowMonotonic6->add($descriptor5);
        $descriptorRowMonotonic6->add($descriptorNullPoints);
        $descriptorRowMonotonic6->add($descriptorNullPoints);
        $descriptorRowMonotonic6->add($descriptor4point5);
        $descriptorRowMonotonic6->add($descriptor2point75);
        $descriptorRowMonotonic6->add($descriptor1);
        $descriptorRowMonotonic6->add($descriptor0);

        /* Non monotonic descriptor rows */
        $descriptorRowNonMonotonic1 = new Collection();
        $descriptorRowNonMonotonic1->add($descriptor7);
        $descriptorRowNonMonotonic1->add($descriptor3);
        $descriptorRowNonMonotonic1->add($descriptor5);
        $descriptorRowNonMonotonic1->add($descriptor6);

        $descriptorRowNonMonotonic2 = new Collection();
        $descriptorRowNonMonotonic2->add($descriptor6);
        $descriptorRowNonMonotonic2->add($descriptor3);
        $descriptorRowNonMonotonic2->add($descriptor4point5);
        $descriptorRowNonMonotonic2->add($descriptor0);

        $descriptorRowNonMonotonic3 = new Collection();
        $descriptorRowNonMonotonic3->add($descriptor0);
        $descriptorRowNonMonotonic3->add($descriptor0);
        $descriptorRowNonMonotonic3->add($descriptor3);
        $descriptorRowNonMonotonic3->add($descriptor6);

        $descriptorRowNonMonotonic4 = new Collection();
        $descriptorRowNonMonotonic4->add($descriptor0);
        $descriptorRowNonMonotonic4->add($descriptorNullPoints);
        $descriptorRowNonMonotonic4->add($descriptor7);
        $descriptorRowNonMonotonic4->add($descriptor2point75);

        $descriptorRowNonMonotonic5 = new Collection();
        $descriptorRowNonMonotonic5->add($descriptor7);
        $descriptorRowNonMonotonic5->add($descriptor6);
        $descriptorRowNonMonotonic5->add($descriptor5);
        $descriptorRowNonMonotonic5->add($descriptor5);
        $descriptorRowNonMonotonic5->add($descriptor1);

        $descriptorRowNonMonotonic6 = new Collection();
        $descriptorRowNonMonotonic6->add($descriptor0);
        $descriptorRowNonMonotonic6->add($descriptor6);
        $descriptorRowNonMonotonic6->add($descriptor2point75);
        $descriptorRowNonMonotonic6->add($descriptor1);

        yield [$hasMarkRange, $descriptorRowMonotonic1, true];
        yield [$hasMarkRange, $descriptorRowMonotonic2, true];
        yield [$hasMarkRange, $descriptorRowMonotonic3, true];
        yield [$hasMarkRange, $descriptorRowMonotonic4, true];
        yield [$hasMarkRange, $descriptorRowMonotonic5, true];
        yield [$hasMarkRange, $descriptorRowMonotonic6, true];

        yield [$noMarkRange, $descriptorRowMonotonic1, true];
        yield [$noMarkRange, $descriptorRowMonotonic2, true];
        yield [$noMarkRange, $descriptorRowMonotonic3, true];
        yield [$noMarkRange, $descriptorRowMonotonic4, true];
        yield [$noMarkRange, $descriptorRowMonotonic5, true];
        yield [$noMarkRange, $descriptorRowMonotonic6, true];

        yield [$hasMarkRange, $descriptorRowNonMonotonic1, false];
        yield [$hasMarkRange, $descriptorRowNonMonotonic2, false];
        yield [$hasMarkRange, $descriptorRowNonMonotonic3, false];
        yield [$hasMarkRange, $descriptorRowNonMonotonic4, false];
        yield [$hasMarkRange, $descriptorRowNonMonotonic5, false];
        yield [$hasMarkRange, $descriptorRowNonMonotonic6, false];

        yield [$noMarkRange, $descriptorRowNonMonotonic1, true];
        yield [$noMarkRange, $descriptorRowNonMonotonic2, true];
        yield [$noMarkRange, $descriptorRowNonMonotonic3, true];
        yield [$noMarkRange, $descriptorRowNonMonotonic4, true];
        yield [$noMarkRange, $descriptorRowNonMonotonic5, true];
        yield [$noMarkRange, $descriptorRowNonMonotonic6, true];
    }

    /**
     *
     * @param bool $usesMarkRange
     * @param Collection $descriptorRow
     * @param bool $expected                 Whether this test should assert true or false
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIsSatisfiedByData')]
    public function testIsSatisfiedBy(bool $usesMarkRange, Collection $descriptorRow, bool $expected)
    {
        $spec = new ValidMonotonicDescriptorRow($usesMarkRange);
        $this->assertSame($expected, $spec->isSatisfiedBy($descriptorRow));
    }
}
