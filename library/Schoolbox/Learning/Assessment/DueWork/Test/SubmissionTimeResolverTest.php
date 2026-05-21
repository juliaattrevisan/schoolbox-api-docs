<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Test;

use Alaress_Date_Format;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\DueWork\Model;
use Schoolbox\Learning\Assessment\DueWork\SubmissionTimeResolver;

#[CoversClass(SubmissionTimeResolver::class)]
class SubmissionTimeResolverTest extends TestCase
{
    private const DATE_FORMAT = Alaress_Date_Format::DATABASE_FORMAT;

    #[DataProvider('provideNowSubmissionCases')]
    public function testResolveUsesNowWhenOnTimeOverrideIsNotAvailable(
        Model $assessment,
        bool $postedPunctual,
        Alaress_Date_Format $now
    ): void {
        $resolver = new SubmissionTimeResolver();

        $submittedAt = $resolver->resolve($assessment, $postedPunctual, $now);

        self::assertNotSame($now, $submittedAt);
        self::assertSame($now->getDatabase(), $submittedAt->getDatabase());
    }

    public static function provideNowSubmissionCases(): array
    {
        $now = self::date('2026-06-01 10:30:00');

        return [
            'future assessment with hidden punctual checkbox posted true' => [
                self::buildAssessment(
                    '2026-05-30 09:00:00',
                    '2026-05-31 09:00:00',
                    '2026-06-12 23:59:00',
                    '2026-06-13 23:59:00'
                ),
                true,
                clone $now,
            ],
            'future assessment with punctual posted false' => [
                self::buildAssessment(
                    '2026-05-30 09:00:00',
                    '2026-05-31 09:00:00',
                    '2026-06-12 23:59:00',
                    '2026-06-13 23:59:00'
                ),
                false,
                clone $now,
            ],
            'overdue assessment with punctual posted false' => [
                self::buildAssessment(
                    '2026-05-30 09:00:00',
                    '2026-05-31 09:00:00',
                    '2026-06-01 09:00:00',
                    '2026-06-03 09:00:00'
                ),
                false,
                clone $now,
            ],
        ];
    }

    #[DataProvider('providePunctualOverrideCases')]
    public function testResolveUsesDueDateWhenOnTimeOverrideIsAvailable(
        Model $assessment,
        Alaress_Date_Format $now
    ): void {
        $resolver = new SubmissionTimeResolver();
        $expected = (clone $assessment->getDueAt())->addSeconds(-1);

        $submittedAt = $resolver->resolve($assessment, true, $now);

        self::assertNotSame($assessment->getDueAt(), $submittedAt);
        self::assertSame($expected->getDatabase(), $submittedAt->getDatabase());
    }

    public static function providePunctualOverrideCases(): array
    {
        return [
            'overdue assessment with punctual posted true' => [
                self::buildAssessment(
                    '2026-05-30 09:00:00',
                    '2026-05-31 09:00:00',
                    '2026-06-01 09:00:00',
                    '2026-06-03 09:00:00'
                ),
                self::date('2026-06-01 10:30:00'),
            ],
            'past close assessment with punctual posted true' => [
                self::buildAssessment(
                    '2026-05-30 09:00:00',
                    '2026-05-31 09:00:00',
                    '2026-06-01 09:00:00',
                    '2026-06-02 09:00:00'
                ),
                self::date('2026-06-03 10:30:00'),
            ],
        ];
    }

    public function testResolveDoesNotMutateAssessmentDueDate(): void
    {
        $assessment = self::buildAssessment(
            '2026-05-30 09:00:00',
            '2026-05-31 09:00:00',
            '2026-06-01 09:00:00',
            '2026-06-03 09:00:00'
        );
        $now = self::date('2026-06-01 10:30:00');
        $dueAt = $assessment->getDueAt();
        $dueAtBefore = $dueAt->getDatabase();
        $resolver = new SubmissionTimeResolver();

        $resolver->resolve($assessment, true, $now);
        $resolver->resolve($assessment, true, $now);

        self::assertSame($dueAtBefore, $assessment->getDueAt()->getDatabase());
        self::assertSame($dueAt, $assessment->getDueAt());
    }

    private static function buildAssessment(
        string $publishAt,
        string $openAt,
        string $dueAt,
        string $closeAt
    ): Model {
        return (new Model())
            ->setAcceptsMultiple()
            ->setPublishAt(self::date($publishAt))
            ->setOpenAt(self::date($openAt))
            ->setDueAt(self::date($dueAt))
            ->setCloseAt(self::date($closeAt));
    }

    private static function date(string $date): Alaress_Date_Format
    {
        return new Alaress_Date_Format($date, self::DATE_FORMAT);
    }
}
