<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Geosnapshot;

use Schoolbox\User;


/**
 * Class ValidGeosnapshotAvailable
 *
 * Decide whether Geosnapshot integration was correclty set up and available
 * to make requests to the third party
 *
 * @package Schoolbox\Geosnapshot
 */
class ValidGeosnapshotAvailable
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
    public function isSatisifiedBy(?string $apiKey, bool $featureFlag, User $viewer, User $viewee): bool
    {

        /* Invalid API key; exit early
         * No connectivity allowed
         */

        /* Feature Flag is off; exit early
         * No connectivity allowed
         */

        /* TODO $viewee has been redacted from photos; exit early
         *  Nothing to see here for anyone
         */

        /* $viewer is a staff member;
         * They can view content for anyone
         */

        /* $viewer is a parent of $viewee
         * They can view content for $viewee but nobody else
         */

        /* Otherwise you're someone else
         * No connectivity allowed
         */
        return false;
    }
}