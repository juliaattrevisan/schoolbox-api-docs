<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Geosnapshot;

/**
 * Class ValidGeosnapshotAvailable
 *
 * Decide if Geosnapshot was properly configured
 *
 * @package Schoolbox\Geosnapshot\Test
 */
class ValidGeosnapshotAvailable
{
    /**
     * @param string|null $apiKey
     * @param bool        $featureFlag
     * @return bool
     */
    public function isConfigured(?string $apiKey, bool $featureFlag): bool
    {

        /* Invalid API key; exit early
         * No connectivity allowed
         *
         * Feature Flag is off; exit early
         * No connectivity allowed
         */
        return ('' !== (string)$apiKey && $featureFlag);
    }
}