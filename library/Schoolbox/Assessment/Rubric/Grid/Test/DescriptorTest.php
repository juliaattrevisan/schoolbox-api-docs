<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\Grid\Descriptor;

class DescriptorTest extends TestCase
{
    /**
     * Data provider for `testAssertValidityValid`.
     *
     * @return iterable
     */
    public function getTestAssertValidityValidData(): iterable
    {
        $descriptor = new Descriptor();
        $descriptor
            ->setMinValue(null)
            ->setMaxValue(null);
        yield 'null' => [$descriptor];


        $descriptor = new Descriptor();
        $descriptor
            ->setMinValue(5)
            ->setMaxValue(5);
        yield 'not null, no range' => [$descriptor];


        $descriptor = new Descriptor();
        $descriptor
            ->setMinValue(5)
            ->setMaxValue(6);
        yield 'not null, has range' => [$descriptor];
    }


    /**
     * @dataProvider getTestAssertValidityValidData
     * @doesNotPerformAssertions
     *
     * @covers \Schoolbox\Assessment\Rubric\Grid\Descriptor::assertValidity
     * @param Descriptor $descriptor
     */
    public function testAssertValidityValid(Descriptor $descriptor): void
    {
        $descriptor->assertValidity();
    }


    /**
     * Data provider for `testAssertValidityInvalid`.
     *
     * @return iterable
     */
    public function getTestAssertValidityInvalidData(): iterable
    {
        $descriptor = new Descriptor();
        $descriptor
            ->setMinValue(null)
            ->setMaxValue(1);
        yield 'max value only' => [$descriptor];


        $descriptor = new Descriptor();
        $descriptor
            ->setMinValue(5)
            ->setMaxValue(null);
        yield 'min value only' => [$descriptor];


        $descriptor = new Descriptor();
        $descriptor
            ->setMinValue(15)
            ->setMaxValue(6);
        yield 'min value > max value' => [$descriptor];
    }


    /**
     * @dataProvider getTestAssertValidityInvalidData
     *
     * @covers \Schoolbox\Assessment\Rubric\Grid\Descriptor::assertValidity
     * @param Descriptor $descriptor
     */
    public function testAssertValidityInvalid(Descriptor $descriptor): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $descriptor->assertValidity();
    }


    /**
     * Data provider for `testRetrieveLeftRightValue`.
     *
     * @return iterable
     */
    public function getTestRetrieveLeftRightValue(): iterable
    {
        // Null value descriptor
        $descriptor = new Descriptor();
        $descriptor
            ->setMinValue(null)
            ->setMaxValue(null)
            ->setDirectionAscending();
        yield 'null values, ascending' => [$descriptor, null, null];

        $descriptor->setDirectionDescending();
        yield 'null values, descending' => [$descriptor, null, null];


        // Single null value: should not exist, but treat as a null value one.
        // Either both min and max must be null, or neither must be.
        $descriptor = new Descriptor();
        $descriptor
            ->setMinValue(null)
            ->setMaxValue(5)
            ->setDirectionAscending();
        yield 'no min value, ascending' => [$descriptor, null, null];

        $descriptor->setDirectionDescending();
        yield 'no min value, descending' => [$descriptor, null, null];

        $descriptor = new Descriptor();
        $descriptor
            ->setMinValue(0)
            ->setMaxValue(null)
            ->setDirectionAscending();
        yield 'no max value, ascending' => [$descriptor, null, null];

        $descriptor->setDirectionDescending();
        yield 'no max value, descending' => [$descriptor, null, null];


        // No range
        $descriptor = new Descriptor();
        $descriptor
            ->setMinValue(8)
            ->setMaxValue(8)
            ->setDirectionAscending();
        yield 'no range, ascending' => [$descriptor, 8, 8];

        $descriptor->setDirectionDescending();
        yield 'no range, descending' => [$descriptor, 8, 8];


        // Ranged descriptor
        $descriptor = new Descriptor();
        $descriptor
            ->setMinValue(1)
            ->setMaxValue(8)
            ->setDirectionAscending();
        yield 'range, ascending' => [$descriptor, 1, 8];

        $descriptor->setDirectionDescending();
        yield 'range, descending' => [$descriptor, 8, 1];
    }


    /**
     * @dataProvider getTestRetrieveLeftRightValue
     *
     * @covers \Schoolbox\Assessment\Rubric\Grid\Descriptor::retrieveLeftValue
     * @param Descriptor $descriptor
     * @param float|null $expectedLeft
     */
    public function testRetrieveLeftValue(Descriptor $descriptor, ?float $expectedLeft): void
    {
        $this->assertSame($expectedLeft, $descriptor->retrieveLeftValue());
    }


    /**
     * @dataProvider getTestRetrieveLeftRightValue
     *
     * @covers \Schoolbox\Assessment\Rubric\Grid\Descriptor::retrieveRightValue
     * @param Descriptor $descriptor
     * @param float|null $_
     * @param float|null $expectedRight
     */
    public function testRetrieveLeftRightValue(Descriptor $descriptor, ?float $_, ?float $expectedRight): void
    {
        $this->assertSame($expectedRight, $descriptor->retrieveRightValue());
    }
}
