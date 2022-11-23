<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News;

use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;

/**
 * Is the user allowed to change the sticky (a.k.a. priority) status of an
 * article?
 */
class ValidChangeStickyStatus
{
    /**
     * Constructor
     *
     * @param bool $configStickyEnabled Is the sticky config setting enabled?
     */
    public function __construct(
        private bool $configStickyEnabled,
        private Access $access,
    ) {
    }

    /**
     * Can the user change the sticky status?
     *
     * They may do so if both:
     * * the sticky config setting is enabled
     * * they are a news moderator or superuser
     *
     * @param User $user
     * @return bool
     */
    public function isSatisfiedBy(User $user): bool
    {
        if (!$this->configStickyEnabled) {
            return false;
        }
        if ($user->isSuperuser()) {
            return true;
        }
        try {
            return $this->access->getAccessLevel($user, UserAccess::SERVICE_NEWS) === UserAccess::ACCESS_FULL;
        } catch (Exception) {
            // Couldn't find news service access for some reason?
            return false;
        }
    }
}
