<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Geosnapshot;

use Schoolbox\User;
use SensitiveParameter;

/**
 * Class ValidPhotosAvailable
 *
 * Decide whether Geosnapshot integration was correctly set up and available
 * to make requests to the third party, for photos of a particular guy
 *
 * @package Schoolbox\Geosnapshot
 */
class ValidPhotosAvailable extends ValidGeosnapshotAvailable
{
    /**
     * Geosnapshot integration is decided by two config vars and two users
     * (the one doing the viewing and the one being viewed)
     *
     * @param string|null $apiKey
     * @param bool        $featureFlag
     * @param User        $viewer
     * @param User        $viewee
     * @return bool
     */
    public function isSatisifiedBy(#[SensitiveParameter] ?string $apiKey, bool $featureFlag, User $viewer, User $viewee): bool
    {
        /* Invalid API key; exit early
         * No connectivity allowed
         *
         * Feature Flag is off; exit early
         * No connectivity allowed
         */
        if (!$this->isConfigured($apiKey, $featureFlag)) {
            return false;
        }

        /* No portrait picture? No trabajo
         */
        if (!$viewee->getPortrait()) {
            return false;
        }

        /* TODO $viewee has been redacted from photos; exit early
         *  Nothing to see here for anyone
         */

        /* $viewer is a student, and they're viewing themselves
         */
        if ($viewer->getRole()->isStudent() && $viewer->getId() === $viewee->getId()) {
            return true;
        }

        /* $viewer is a staff member;
         * They can view content for anyone
         */
        if ($viewer->getRole()->isStaff()) {
            return true;
        }

        /* $viewer is a parent of $viewee
         * They can view content for $viewee but nobody else
         */
        if ($viewer->isParentOf($viewee)) {
            return true;
        }

        /* Otherwise you're someone else
         * No connectivity allowed
         */
        return false;
    }
}
