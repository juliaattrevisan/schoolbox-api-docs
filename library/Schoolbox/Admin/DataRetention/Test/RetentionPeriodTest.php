<?php

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\DataRetention\RetentionPeriod;
use Schoolbox\Application\Exception\Exception as SchoolboxException;

/**
 * Tests for the RetentionPeriod helper class.
 *
 * These tests follow the docs/mvc example style: they use PHPUnit DataProviders
 * for multiple-case coverage, test both expected successes and failure modes,
 * and explicitly assert PHP-level type errors where applicable.
 *
 * Key behaviours covered:
 *  - default construction state
 *  - validation and computation performed by calculateDate(int $days)
 *  - constructor behaviour for allowed inputs (int and null)
 *  - that calculateDate() uses the first computed value and ignores subsequent calls
 *  - TypeError behaviour when the constructor receives incompatible types
 */
class RetentionPeriodTest extends TestCase
{
    public function testDefaultConstructionRetainsIndefinitely(): void
    {
        // When no retention days are provided in the constructor, the object
        // should indicate "retain indefinitely". The class represents this
        // state as 0 days and an empty database date string.
        $rp = new RetentionPeriod();

        // By convention getRetentionDays() returns 0 when no value is set.
        $this->assertSame(0, $rp->getRetentionDays());

        // Current implementation returns an empty string for the database cutoff
        // when retention is disabled; assert that behaviour explicitly.
        $this->assertSame('', $rp->getRetentionDatabase());
    }

    /**
     * Test valid integer retention calculations by calling calculateDate().
     * The constructor accepts int|null, so we avoid passing strings here.
     *
     * @param int $inputDays
     */
    #[DataProvider('providerValidIntegerRetentionPeriods')]
    public function testValidIntegerRetentionPeriods(int $inputDays): void
    {
        // Use an instance with no pre-set constructor value and call calculateDate().
        // calculateDate(int $days) is responsible for validating and computing
        // the database-formatted cutoff date via the project's date helper.
        $rp = new RetentionPeriod();

        // Capture the returned database date string from the method under test.
        $actual = $rp->calculateDate($inputDays);

        // The call should have stored the validated days value in the object.
        $this->assertSame($inputDays, $rp->getRetentionDays());

        // Build the expected representation using the same helper the class
        // uses so the assertion is robust to formatting behaviour.
        $expected = \Alaress_Date_Format::newNaturalDate(sprintf('-%d days', $inputDays))->getDatabaseDate();

        // The computed database date should match the expected value.
        $this->assertSame($expected, $actual);
    }

    public static function providerValidIntegerRetentionPeriods(): array
    {
        return [
            'int 1 day' => [1],
            'int 10 days' => [10],
            'int 365 days' => [365],
        ];
    }

    /**
     * Invalid integer inputs passed to calculateDate should raise SchoolboxException
     * because ValidRetentionPeriod will reject them.
     *
     * @param int $input
     */
    #[DataProvider('providerInvalidIntegerRetentionPeriods')]
    public function testInvalidIntegerRetentionPeriodsRaiseException(int $input): void
    {
        // For certain integer inputs the ValidRetentionPeriod spec will
        // reject the value and the RetentionPeriod::calculateDate() method
        // is expected to propagate a Schoolbox exception. We assert that here.
        $this->expectException(SchoolboxException::class);

        $rp = new RetentionPeriod();

        // Calling calculateDate with an invalid integer should raise the exception.
        $rp->calculateDate($input);
    }

    public static function providerInvalidIntegerRetentionPeriods(): array
    {
        return [
            'zero days' => [0],
            'negative days' => [-1],
        ];
    }


    /**
     * Ensure constructor accepts valid inputs (int and null) and that the object state reflects those values.
     *
     * @param int|null $input
     * @param int $expectedDays
     */
    #[DataProvider('providerConstructorValidValues')]
    public function testConstructorAcceptsValidValues(int|null $input, int $expectedDays): void
    {
        // The constructor accepts only the types the production class allows
        // (int|null). This test exercises that path and confirms the object
        // state after construction.
        $rp = new RetentionPeriod($input);

        // The stored days value should reflect the constructor argument (or 0).
        $this->assertSame($expectedDays, $rp->getRetentionDays());

        // Note: the current implementation does not compute or expose a
        // database date string via the constructor path; it returns an empty
        // string. We assert that behaviour explicitly so the test documents it.
        $this->assertSame('', $rp->getRetentionDatabase());
    }

    public static function providerConstructorValidValues(): array
    {
        return [
            'null disables retention' => [null, 0],
            'int 10 days' => [10, 10],
        ];
    }

    public function testConstructorValueIsRespectedWhenCalculatingDateWithDifferentArgument(): void
    {
        // If the object was constructed with a days value, subsequent
        // calculateDate() calls should honour the stored value rather than
        // recalculating from the new argument. This mirrors the class' behaviour
        // of caching the first computed date.
        $rp = new RetentionPeriod(10);

        // Pass a different value to calculateDate; implementation is expected
        // to use the constructed value (10) rather than the supplied 1.
        $actual = $rp->calculateDate(1);

        // Stored days remain the constructor-provided value.
        $this->assertSame(10, $rp->getRetentionDays());

        // Expect the date that corresponds to '-10 days'. Use the project's
        // helper for a consistent expected format.
        $expected = \Alaress_Date_Format::newNaturalDate('-10 days')->getDatabaseDate();
        $this->assertSame($expected, $actual);
    }


    public function testCalculateDateUsesFirstComputedValueAndIgnoresSubsequentArguments(): void
    {
        // When calculateDate() is called the first time it computes and caches
        // the date. Subsequent calls should reuse that cached value, ignoring
        // any different days argument passed in later calls.
        $rp = new RetentionPeriod();

        // First computation with 10 days
        $first = $rp->calculateDate(10);
        $this->assertSame(10, $rp->getRetentionDays());

        // A subsequent call with a different days value should return the same first-computed date
        $second = $rp->calculateDate(20);
        $this->assertSame($first, $second);
        $this->assertSame(10, $rp->getRetentionDays());
    }


    /**
     * Verify that values with incompatible types (not int|string|null) raise a TypeError.
     * This follows the docs/mvc example pattern for testing type errors.
     *
     * @param mixed $input
     */
    #[DataProvider('getTestErrorRaisedData')]
    public function testErrorRaised(mixed $input): void
    {
        // The constructor for RetentionPeriod currently requires an int or null
        // (it will type-hint for ?int in production code). Values of incompatible
        // PHP types (float, object, array, resource) should cause a TypeError
        // to be thrown by the PHP engine. Assert that behaviour here.
        $this->expectException(\TypeError::class);

        // Attempt construction with an explicitly invalid type.
        new RetentionPeriod($input);
    }


    public static function getTestErrorRaisedData(): array
    {
        return [
            [1.5],              // float
            [new \stdClass()],  // object
            [[]],               // array
            [STDOUT],           // resource
            ['3.14'],           // non-integer numeric string
            ['abc'],            // non-numeric string
        ];
    }
}
