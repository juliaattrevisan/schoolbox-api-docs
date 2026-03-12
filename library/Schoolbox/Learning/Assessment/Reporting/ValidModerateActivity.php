<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Reporting;

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;

class ValidModerateActivity
{
    /**
     * In order to manage reporting activities:
     *     - Enable Reporting Activity must be enabled in admin
     *     - the $user provided must be an LMS Moderator or a superuser
     *
     * @param bool $featureFlag
     * @param User $user
     * @return bool
     */
    public function isSatisfiedBy(bool $featureFlag, \Schoolbox\User $user): bool
    {
        /* Feature Flag must be enabled
         */
        if ($featureFlag) {
            /* Superusers are allowed
             */
            if ($user->isSuperuser()) {
                return true;
            }

            /* Any LMS service permission with WRITE+ access is allowed
             */
            foreach ($user->getAccessList() as $access) {
                if ($access->getService() === UserAccess::SERVICE_LMS && $access->getAccessLevel() >= UserAccess::ACCESS_WRITE) {
                    return true;
                }
            }
        }

        /* Everyone else is disallowed including other staff
         * Unlike every other learning activity the ability to moderate a reporting activity (create them, change their
         * settings, delete them, etc) is bureaucratically controlled.
         *
         * Staff are able to make feedbacks against them, though
         */
        return false;
    }
}
