<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Award;

use Alaress_Date_Format;

/**
 * Helper class for determining a badge's expiry.
 */
class ExpiryHelper
{
    /**
     * Determines a badge's expiry, based upon:
     * * its badge's goal/expiry days
     * * its status (is it a goal or achievement)
     * * its award date
     *
     * @param Model $award
     * @return Alaress_Date_Format|null
     */
    public function determineExpiry(Model $award): ?Alaress_Date_Format
    {
        $badge = $award->getBadge();
        if ($award->isAchievement()) {
            $expiry = $badge->getAchievementExpiry();
        } elseif ($award->isGoal()) {
            $expiry = $badge->getGoalExpiry();
        } else {
            return null;
        }

        return $expiry ? (clone $award->getAwardedAt())->addDays($expiry) : null;
    }
}
