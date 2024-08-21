<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Award\Test;

use Alaress_Date_Format;
use Error;
use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Badge;

/**
 * Test for `\Schoolbox\Learning\Badge\Award\ExpiryHelper`.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Learning\Badge\Award\ExpiryHelper::class)]
class ExpiryHelperTest extends TestCase
{
    /**
     * Data provider for `testDetermineExpiry`.
     *
     * @return iterable
     */
    public function getTestDetermineExpiryData(): iterable
    {
        $awardedAt = Alaress_Date_Format::newDatabaseDate('2021-01-01 00:00:00');
        yield 'goal never expires' => [
            null,
            $this->generateBadgeAward(
                Badge\Model::EXPIRY_NEVER,
                Badge\Model::EXPIRY_NEVER,
                Badge\Award\Model::AWARD_STATUS_GOAL,
                $awardedAt
            ),
        ];
        yield 'achievement never expires' => [
            null,
            $this->generateBadgeAward(
                Badge\Model::EXPIRY_NEVER,
                Badge\Model::EXPIRY_NEVER,
                Badge\Award\Model::AWARD_STATUS_ACHIEVEMENT,
                $awardedAt
            ),
        ];

        $dayExpiry = Alaress_Date_Format::newDatabaseDate('2021-01-02 00:00:00');
        yield 'goal expires in 1 day' => [
            $dayExpiry,
            $this->generateBadgeAward(
                Badge\Model::EXPIRY_NEVER,
                Badge\Model::EXPIRY_DAY,
                Badge\Award\Model::AWARD_STATUS_GOAL,
                $awardedAt
            ),
        ];
        yield 'achievement expires in 1 day' => [
            $dayExpiry,
            $this->generateBadgeAward(
                Badge\Model::EXPIRY_DAY,
                Badge\Model::EXPIRY_NEVER,
                Badge\Award\Model::AWARD_STATUS_ACHIEVEMENT,
                $awardedAt
            ),
        ];

        $weekExpiry = Alaress_Date_Format::newDatabaseDate('2021-01-08 00:00:00');
        yield 'goal expires in 1 week' => [
            $weekExpiry,
            $this->generateBadgeAward(
                Badge\Model::EXPIRY_NEVER,
                Badge\Model::EXPIRY_WEEK,
                Badge\Award\Model::AWARD_STATUS_GOAL,
                $awardedAt
            ),
        ];
        yield 'achievement expires in 1 week' => [
            $weekExpiry,
            $this->generateBadgeAward(
                Badge\Model::EXPIRY_WEEK,
                Badge\Model::EXPIRY_NEVER,
                Badge\Award\Model::AWARD_STATUS_ACHIEVEMENT,
                $awardedAt
            ),
        ];

        $fortnightExpiry = Alaress_Date_Format::newDatabaseDate('2021-01-15 00:00:00');
        yield 'goal expires in 1 fortnight' => [
            $fortnightExpiry,
            $this->generateBadgeAward(
                Badge\Model::EXPIRY_NEVER,
                Badge\Model::EXPIRY_FORTNIGHT,
                Badge\Award\Model::AWARD_STATUS_GOAL,
                $awardedAt
            ),
        ];
        yield 'achievement expires in 1 fortnight' => [
            $fortnightExpiry,
            $this->generateBadgeAward(
                Badge\Model::EXPIRY_FORTNIGHT,
                Badge\Model::EXPIRY_NEVER,
                Badge\Award\Model::AWARD_STATUS_ACHIEVEMENT,
                $awardedAt
            ),
        ];

        // 1 month = 31 days
        $monthExpiry = Alaress_Date_Format::newDatabaseDate('2021-02-01 00:00:00');
        yield 'goal expires in 1 month' => [
            $monthExpiry,
            $this->generateBadgeAward(
                Badge\Model::EXPIRY_NEVER,
                Badge\Model::EXPIRY_MONTH,
                Badge\Award\Model::AWARD_STATUS_GOAL,
                $awardedAt
            ),
        ];
        yield 'achievement expires in 1 month' => [
            $monthExpiry,
            $this->generateBadgeAward(
                Badge\Model::EXPIRY_MONTH,
                Badge\Model::EXPIRY_NEVER,
                Badge\Award\Model::AWARD_STATUS_ACHIEVEMENT,
                $awardedAt
            ),
        ];

        // 3 months = 92 days
        // 31 days in Jan, 28 days in Feb, 31 in Mar = 90 days till Apr 1
        $quarterYearExpiry = Alaress_Date_Format::newDatabaseDate('2021-04-03 00:00:00');
        yield 'goal expires in 3 months' => [
            $quarterYearExpiry,
            $this->generateBadgeAward(
                Badge\Model::EXPIRY_NEVER,
                Badge\Model::EXPIRY_QUARTER_YEAR,
                Badge\Award\Model::AWARD_STATUS_GOAL,
                $awardedAt
            ),
        ];
        yield 'achievement expires in 3 months' => [
            $quarterYearExpiry,
            $this->generateBadgeAward(
                Badge\Model::EXPIRY_QUARTER_YEAR,
                Badge\Model::EXPIRY_NEVER,
                Badge\Award\Model::AWARD_STATUS_ACHIEVEMENT,
                $awardedAt
            ),
        ];

        // 6 months = 183 days
        // 31 days in Jan, 28 days in Feb, 31 in Mar = 90 days till Apr 1
        // 30 days in Apr, 31 days in May, 30 in Jun = 91 days till Jul 1
        $halfYearExpiry = Alaress_Date_Format::newDatabaseDate('2021-07-03 00:00:00');
        yield 'goal expires in 6 months' => [
            $halfYearExpiry,
            $this->generateBadgeAward(
                Badge\Model::EXPIRY_NEVER,
                Badge\Model::EXPIRY_HALF_YEAR,
                Badge\Award\Model::AWARD_STATUS_GOAL,
                $awardedAt
            ),
        ];
        yield 'achievement expires in 6 months' => [
            $halfYearExpiry,
            $this->generateBadgeAward(
                Badge\Model::EXPIRY_HALF_YEAR,
                Badge\Model::EXPIRY_NEVER,
                Badge\Award\Model::AWARD_STATUS_ACHIEVEMENT,
                $awardedAt
            ),
        ];

        $yearExpiry = Alaress_Date_Format::newDatabaseDate('2022-01-01 00:00:00');
        yield 'goal expires in 1 year' => [
            $yearExpiry,
            $this->generateBadgeAward(
                Badge\Model::EXPIRY_NEVER,
                Badge\Model::EXPIRY_YEAR,
                Badge\Award\Model::AWARD_STATUS_GOAL,
                $awardedAt
            ),
        ];
        yield 'achievement expires in 1 year' => [
            $yearExpiry,
            $this->generateBadgeAward(
                Badge\Model::EXPIRY_YEAR,
                Badge\Model::EXPIRY_NEVER,
                Badge\Award\Model::AWARD_STATUS_ACHIEVEMENT,
                $awardedAt
            ),
        ];
    }

    /**
     * @param Alaress_Date_Format|null $expected
     * @param Badge\Award\Model        $award
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestDetermineExpiryData')]
    public function testDetermineExpiry(?Alaress_Date_Format $expected, Badge\Award\Model $award): void
    {
        $actual = (new Badge\Award\ExpiryHelper())->determineExpiry($award);
        if ($expected) {
            $this->assertEquals($expected->getDatabase(), $actual->getDatabase());
        } else {
            $this->assertNull($actual);
        }
    }

    /**
     * Data provider for `testDetermineExpiryOnIncompleteObject`.
     *
     * @return iterable
     */
    public static function getTestDetermineExpiryOnIncompleteObjectData(): iterable
    {
        $awardedAt = Alaress_Date_Format::newDatabaseDate('2021-01-01 00:00:00');
        yield 'no badge' => [
            (new Badge\Award\Model())
                ->setAwardedAt($awardedAt)
                ->setStatus(Badge\Award\Model::AWARD_STATUS_ACHIEVEMENT)
        ];
        yield 'badge has no goal expiry' => [
            (new Badge\Award\Model())
                ->setBadge((new Badge\Model())->setAchievementExpiry(Badge\Model::EXPIRY_MONTH))
                ->setAwardedAt($awardedAt)
                ->setStatus(Badge\Award\Model::AWARD_STATUS_GOAL)
        ];
        yield 'badge has no achievement expiry' => [
            (new Badge\Award\Model())
                ->setBadge((new Badge\Model())->setGoalExpiry(Badge\Model::EXPIRY_MONTH))
                ->setAwardedAt($awardedAt)
                ->setStatus(Badge\Award\Model::AWARD_STATUS_ACHIEVEMENT)
        ];
        yield 'no status' => [
            (new Badge\Award\Model())
                ->setBadge(
                    (new Badge\Model())
                        ->setGoalExpiry(Badge\Model::EXPIRY_MONTH)
                        ->setAchievementExpiry(Badge\Model::EXPIRY_MONTH)
                )
                ->setAwardedAt($awardedAt)
        ];
        yield 'no awarded at date' => [
            (new Badge\Award\Model())
                ->setBadge(
                    (new Badge\Model())
                        ->setGoalExpiry(Badge\Model::EXPIRY_MONTH)
                        ->setAchievementExpiry(Badge\Model::EXPIRY_MONTH)
                )
                ->setStatus(Badge\Award\Model::AWARD_STATUS_ACHIEVEMENT)
        ];
    }

    /**
     * Test for expected errors which occur when the badge award isn't fully
     * instantiated.
     *
     * @param Badge\Award\Model $award
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestDetermineExpiryOnIncompleteObjectData')]
    public function testDetermineExpiryOnIncompleteObject(Badge\Award\Model $award): void
    {
        // Whilst warning messages can no longer be expected, errors can
        $this->expectException(Error::class);
        (new Badge\Award\ExpiryHelper())->determineExpiry($award);
    }

    /**
     * Helper function for generating a badge award for testing.
     *
     * @param int|null            $achievementExpiry
     * @param int|null            $goalExpiry
     * @param string              $awardStatus
     * @param Alaress_Date_Format $awardedAt
     * @return Badge\Award\Model
     */
    private function generateBadgeAward(
        ?int $achievementExpiry,
        ?int $goalExpiry,
        string $awardStatus,
        Alaress_Date_Format $awardedAt
    ): Badge\Award\Model {
        $badge = (new Badge\Model())
            ->setAchievementExpiry($achievementExpiry)
            ->setGoalExpiry($goalExpiry);
        return (new Badge\Award\Model())
            ->setBadge($badge)
            ->setStatus($awardStatus)
            ->setAwardedAt($awardedAt);
    }
}
