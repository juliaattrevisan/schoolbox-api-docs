<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Geosnapshot;

/**
 * Class ValidEventPhotosAvailable
 *
 * Decide if you can access Geosnapshot to find galleries that match
 * the specified search term
 *
 * @package Schoolbox\Geosnapshot
 */
class ValidEventPhotosAvailable
{

    /**
     * @param string|null $apiKey
     * @param bool        $featureFlag
     * @param string|null $eventName
     * @return bool
     */
    public function isSatisfiedBy(?string $apiKey, bool $featureFlag, ?string $eventName): bool
    {
        /* Invalid API key; exit early
         * No connectivity allowed
         */
        if ('' === (string)$apiKey) {
            return false;
        }

        /* Feature Flag is off; exit early
         * No connectivity allowed
         */
        if (!$featureFlag) {
            return false;
        }

        /* Search string is required; if provided, go searching for it in Geosnapshot
         */
        if ('' !== trim((string)$eventName)) {
            return true;
        }

        /* Nothing to do
         */
        return false;
    }
}