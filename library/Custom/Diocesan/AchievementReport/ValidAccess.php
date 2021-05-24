<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport;

use Schoolbox\User;

/**
 * Class ValidAccess
 *
 * There's a config var which prevents people from accessing the Achievement Reports
 *
 * The config var should restrict parents and students from accessing the reports, but
 * should always allow staff
 *
 * @package Custom\Diocesan\AchievementReport
 */
class ValidAccess
{

    /**
     * Decide if $user can access the Achievement Reports based on the $configVar
     * dio_achievement_report_enabled
     *
     * @param bool $configVar
     * @param User $user
     * @return bool
     */
    public function isSatisfiedBy(bool $configVar, User $user): bool
    {
        /* Superusers and Staff can always get in
         */
        if ($user->isSuperuser() || $user->getRole()->isStaff()) {
            return true;
        }

        /* Parents and students access is conditional based on config var
         * dio_achievement_report_enabled
         */
        if ($configVar && ($user->getRole()->isParent() || $user->getRole()->isStudent())) {
            return true;
        }

        /* Guests don't get access; anyone else doesn't get access
         */
        return false;
    }
}
