<?php

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\DataRetention\RetentionPeriod;
use Schoolbox\Application\Exception\Exception as SchoolboxException;

class RetentionPeriodTest extends TestCase
{
    /**
     * The provider format acts as a registry of covered behaviours so future
     * changes can be evaluated by scanning inputs against explicit outcomes,
     * rather than reverse-engineering assertions from test logic.
     */
    #[DataProvider('providerValidExample')]
    public function testValidExample(?string $now, ?int $defaultDays, ?int $days, ?int $expectedDays, string $expected): void
    {
        $retentionPeriod = new RetentionPeriod(
            $this->buildNow($now),
            $defaultDays,
            $days,
        );

        $this->assertSame($expected, $retentionPeriod->getRetentionDatabase());
        $this->assertSame($expectedDays, $retentionPeriod->getRetentionDays());
    }

    /**
     * Uses fixed timestamps so the expected outcomes stay literal and easy to
     * audit. That keeps the tests focused on retention behaviour rather than on
     * duplicating date arithmetic inside the assertions.
     */
    public static function providerValidExample(): array
    {
        return [
            'Default construction retains indefinitely' => [
                null,
                null,
                null,
                null,
                '',
            ],
            'Specific retention days override default' => [
                '2001-12-13 01:02:46',
                10,
                4,
                4,
                '2001-12-09 01:02:46',
            ],
            'Use default admin setting' => [
                '2001-12-13 01:02:46',
                10,
                null,
                10,
                '2001-12-03 01:02:46',
            ],
            'Leap year date subtracts correctly' => [
                '2024-03-01 12:00:00',
                2,
                null,
                2,
                '2024-02-28 12:00:00',
            ],
        ];
    }

    /**
     * Separates lazy resolution from construction-time expectations.
     *
     * These cases document which source wins when the class is asked to resolve
     * a cutoff: explicit days, default admin days, or the value passed to
     * calculateDate().
     */
    #[DataProvider('providerCalculatedExample')]
    public function testCalculatedExample(?string $now, ?int $defaultDays, ?int $days, int $calculatedDays, int $expectedDays, string $expected): void
    {
        $retentionPeriod = new RetentionPeriod(
            $this->buildNow($now),
            $defaultDays,
            $days,
        );

        $this->assertSame($expected, $retentionPeriod->calculateDate($calculatedDays));
        $this->assertSame($expectedDays, $retentionPeriod->getRetentionDays());
        $this->assertSame($expected, $retentionPeriod->getRetentionDatabase());
    }

    /**
     * Fixed examples make precedence rules obvious without embedding date maths
     * in the test body.
     */
    public static function providerCalculatedExample(): array
    {
        return [
            'Calculated days are used when nothing is preset' => [
                '2001-12-13 01:02:46',
                null,
                null,
                4,
                4,
                '2001-12-09 01:02:46',
            ],
            'Explicit days win over later calculateDate input' => [
                '2001-12-13 01:02:46',
                null,
                10,
                1,
                10,
                '2001-12-03 01:02:46',
            ],
            'Default days win over later calculateDate input' => [
                '2001-12-13 01:02:46',
                10,
                null,
                1,
                10,
                '2001-12-03 01:02:46',
            ],
        ];
    }

    /**
     * Retention processing should use one stable cutoff for the life of the
     * helper. Recomputing against different day counts later in the same run
     * would make deletion decisions inconsistent.
     */
    #[DataProvider('providerImmutableRetentionDate')]
    public function testImmutableRetentionDate(?string $now, ?int $defaultDays, ?int $days, int $firstDays, int $secondDays, string $expected): void
    {
        $retentionPeriod = new RetentionPeriod(
            $this->buildNow($now),
            $defaultDays,
            $days,
        );

        $first = $retentionPeriod->calculateDate($firstDays);
        $second = $retentionPeriod->calculateDate($secondDays);

        $this->assertSame($expected, $first);
        $this->assertSame($first, $second);
    }

    /**
     * A single example is enough here because the behaviour under test is the
     * caching of the first derived date, not the arithmetic itself.
     */
    public static function providerImmutableRetentionDate(): array
    {
        return [
            'First calculated date is reused' => [
                '2001-12-13 01:02:46',
                null,
                null,
                10,
                20,
                '2001-12-03 01:02:46',
            ],
        ];
    }

    /**
     * Invalid values must fail loudly so a bad retention configuration cannot
     * silently broaden or narrow deletion scope.
     */
    #[DataProvider('providerInvalidRetentionPeriods')]
    public function testInvalidRetentionPeriodsRaiseException(?string $now, ?int $defaultDays, ?int $days, int $calculatedDays): void
    {
        $this->expectException(SchoolboxException::class);

        $retentionPeriod = new RetentionPeriod(
            $this->buildNow($now),
            $defaultDays,
            $days,
        );

        $retentionPeriod->calculateDate($calculatedDays);
    }

    /**
     * Keeps unsafe boundary values visible as named cases.
     */
    public static function providerInvalidRetentionPeriods(): array
    {
        return [
            'Zero days are invalid when calculated' => [
                '2001-12-13 01:02:46',
                null,
                null,
                0,
            ],
            'Negative days are invalid when calculated' => [
                '2001-12-13 01:02:46',
                null,
                null,
                -1,
            ],
            'Invalid default days are rejected when resolved' => [
                '2001-12-13 01:02:46',
                0,
                null,
                4,
            ],
        ];
    }

    /**
     * The existing one-argument constructor is still used in production, so this
     * test protects backward compatibility while the richer constructor form is
     * available for deterministic unit tests.
     */
    public function testLegacySingleIntegerConstructorRemainsSupported(): void
    {
        $retentionPeriod = new RetentionPeriod(10);

        $this->assertSame(10, $retentionPeriod->getRetentionDays());
    }

    /**
     * Type errors are part of the contract here because invalid constructor
     * input should be rejected before any retention logic runs.
     */
    #[DataProvider('getTestErrorRaisedData')]
    public function testErrorRaised(mixed $input): void
    {
        $this->expectException(\TypeError::class);

        new RetentionPeriod($input);
    }

    /**
     * Lists constructor inputs that should be rejected by PHP typing rather than
     * normalised by the helper.
     */
    public static function getTestErrorRaisedData(): array
    {
        return [
            [1.5],
            [new \stdClass()],
            [[]],
            [STDOUT],
            ['3.14'],
            ['abc'],
            [''],
        ];
    }

    /**
     * Centralises test-date construction so provider cases can stay literal and
     * easy to read.
     */
    private function buildNow(?string $now): ?\Alaress_Date_Format
    {
        if ($now === null) {
            return null;
        }

        return new \Alaress_Date_Format($now, \Alaress_Date_Format::DATABASE_FORMAT);
    }
}
