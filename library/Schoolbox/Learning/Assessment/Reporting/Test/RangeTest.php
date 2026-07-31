<?php

/**
 * @author      Codex <codex@openai.com>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Reporting\Test;

use Alaress_Date_Format;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Reporting\Range;

#[CoversClass(Range::class)]
class RangeTest extends TestCase
{
    /**
     * Resolve each configured range to its expected inclusive boundaries.
     *
     * @param string $range Configured supporting-data range value.
     * @param string $expectedFrom Expected database date and time for the lower boundary.
     * @param string $expectedTo Expected database date and time for the upper boundary.
     * @return void
     */
    #[DataProvider('rangeProvider')]
    public function testResolveReturnsConfiguredDateRanges(
        string $range,
        string $expectedFrom,
        string $expectedTo,
    ): void {
        $referenceDate = $this->referenceDate();
        $currentTerm = $this->term(2, '2026-04-20 09:00:00', '2026-06-27 15:00:00');
        $previousTerm = $this->term(1, '2026-01-27 09:00:00', '2026-04-17 15:00:00');
        $futureTerm = $this->term(3, '2026-07-20 09:00:00', '2026-09-25 15:00:00');

        $termMapper = $this->createStub(TermMapper::class);
        $termMapper->method('findForDate')->willReturn($currentTerm);
        $termMapper->method('findAllNonExtraordinary')->willReturn(
            new GenericCollection(Term::class, [$futureTerm, $currentTerm, $previousTerm]),
        );

        $resolved = Range::from($range)->resolve($referenceDate, $termMapper);

        self::assertNotNull($resolved);
        self::assertSame($expectedFrom, $resolved['from']->getDatabase());
        self::assertSame($expectedTo, $resolved['to']->getDatabase());
    }

    /**
     * Provide the configured ranges and their expected inclusive boundaries.
     *
     * @return array<string,array{string,string,string}>
     */
    public static function rangeProvider(): array
    {
        return [
            'current academic period' => [
                Range::CURRENT_ACADEMIC_PERIOD->value,
                '2026-04-20 09:00:00',
                '2026-06-27 15:00:00',
            ],
            'previous academic period' => [
                Range::PREVIOUS_ACADEMIC_PERIOD->value,
                '2026-01-27 09:00:00',
                '2026-04-17 15:00:00',
            ],
            'current and previous academic period' => [
                Range::CURRENT_AND_PREVIOUS_ACADEMIC_PERIOD->value,
                '2026-01-27 09:00:00',
                '2026-06-27 15:00:00',
            ],
            'current academic year' => [
                Range::CURRENT_ACADEMIC_YEAR->value,
                '2026-01-01 00:00:00',
                '2026-12-31 23:59:59',
            ],
            'current and previous academic year' => [
                Range::CURRENT_AND_PREVIOUS_ACADEMIC_YEAR->value,
                '2025-01-01 00:00:00',
                '2026-12-31 23:59:59',
            ],
        ];
    }

    /**
     * Test future terms are not considered previous to the current term.
     *
     * @return void
     */
    public function testResolveDoesNotSelectFuturePeriodAsPrevious(): void
    {
        $referenceDate = $this->referenceDate();
        $currentTerm = $this->term(2, '2026-04-20 09:00:00', '2026-06-27 15:00:00');
        $futureTerm = $this->term(3, '2026-07-20 09:00:00', '2026-09-25 15:00:00');
        $termMapper = $this->createStub(TermMapper::class);
        $termMapper->method('findForDate')->willReturn($currentTerm);
        $termMapper->method('findAllNonExtraordinary')->willReturn(new GenericCollection(Term::class, [$futureTerm, $currentTerm]));

        self::assertNull(Range::PREVIOUS_ACADEMIC_PERIOD->resolve($referenceDate, $termMapper));
    }

    /**
     * Test period ranges are unresolved when no current ordinary term exists.
     *
     * @return void
     */
    public function testResolveReturnsNullWhenCurrentPeriodIsMissing(): void
    {
        $referenceDate = $this->referenceDate();
        $termMapper = $this->createStub(TermMapper::class);
        $termMapper->method('findForDate')->willReturn(new Term());

        self::assertNull(Range::CURRENT_ACADEMIC_PERIOD->resolve($referenceDate, $termMapper));
        self::assertNull(Range::PREVIOUS_ACADEMIC_PERIOD->resolve($referenceDate, $termMapper));
        self::assertNull(Range::CURRENT_AND_PREVIOUS_ACADEMIC_PERIOD->resolve($referenceDate, $termMapper));
    }

    /**
     * Test a missing previous term makes the previous-only range unresolved.
     *
     * @return void
     */
    public function testResolveReturnsNullWhenPreviousPeriodIsMissing(): void
    {
        $referenceDate = $this->referenceDate();
        $currentTerm = $this->term(2, '2026-04-20 09:00:00', '2026-06-27 15:00:00');
        $termMapper = $this->createStub(TermMapper::class);
        $termMapper->method('findForDate')->willReturn($currentTerm);
        $termMapper->method('findAllNonExtraordinary')->willReturn(new GenericCollection(Term::class, [$currentTerm]));

        self::assertNull(Range::PREVIOUS_ACADEMIC_PERIOD->resolve($referenceDate, $termMapper));
    }

    /**
     * Test a combined period range falls back to the current term when no previous term exists.
     *
     * @return void
     */
    public function testResolveCurrentAndPreviousPeriodFallsBackToCurrentPeriod(): void
    {
        $referenceDate = $this->referenceDate();
        $currentTerm = $this->term(2, '2026-04-20 09:00:00', '2026-06-27 15:00:00');
        $termMapper = $this->createStub(TermMapper::class);
        $termMapper->method('findForDate')->willReturn($currentTerm);
        $termMapper->method('findAllNonExtraordinary')->willReturn(new GenericCollection(Term::class, [$currentTerm]));

        $resolved = Range::CURRENT_AND_PREVIOUS_ACADEMIC_PERIOD->resolve($referenceDate, $termMapper);

        self::assertNotNull($resolved);
        self::assertSame('2026-04-20 09:00:00', $resolved['from']->getDatabase());
        self::assertSame('2026-06-27 15:00:00', $resolved['to']->getDatabase());
    }

    /**
     * Test the resolved term dates can be safely mutated without changing the term models.
     *
     * @return void
     */
    public function testResolveClonesTermDates(): void
    {
        $referenceDate = $this->referenceDate();
        $currentTerm = $this->term(2, '2026-04-20 09:00:00', '2026-06-27 15:00:00');
        $termMapper = $this->createStub(TermMapper::class);
        $termMapper->method('findForDate')->willReturn($currentTerm);

        $resolved = Range::CURRENT_ACADEMIC_PERIOD->resolve($referenceDate, $termMapper);

        self::assertNotNull($resolved);
        $resolved['from']->startOfDay();
        $resolved['to']->endOfDay();
        self::assertSame('2026-04-20 09:00:00', $currentTerm->getStart()->getDatabase());
        self::assertSame('2026-06-27 15:00:00', $currentTerm->getDisplayFinish()->getDatabase());
    }

    /**
     * Create the reference date shared by the deterministic term cases.
     *
     * @return Alaress_Date_Format
     */
    private static function referenceDate(): Alaress_Date_Format
    {
        return new Alaress_Date_Format(
            '2026-05-15 12:00:00',
            Alaress_Date_Format::DATABASE_FORMAT,
            null,
            new \Alaress_Date_Locale_Static(),
        );
    }

    /**
     * Create a persisted ordinary term with the supplied displayed boundaries.
     *
     * @param int $id Persisted term identifier.
     * @param string $start Database date and time for the term start.
     * @param string $displayFinish Database date and time for the displayed finish.
     * @return Term
     */
    private static function term(int $id, string $start, string $displayFinish): Term
    {
        return (new Term())
            ->setId($id)
            ->setStart(new Alaress_Date_Format($start, Alaress_Date_Format::DATABASE_FORMAT))
            ->setDisplayFinish(new Alaress_Date_Format($displayFinish, Alaress_Date_Format::DATABASE_FORMAT));
    }
}
