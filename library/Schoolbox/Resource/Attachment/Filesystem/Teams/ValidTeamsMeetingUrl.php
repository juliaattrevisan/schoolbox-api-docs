<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Attachment\Filesystem\Teams;

/**
 * Class ValidTeamsMeetingUrl
 *
 * Decide whether the url provided basically matches the Microsoft Teams meeting url
 * that we expect to receive. We're taking a modified middleware and basically putting
 * content into url params, so it could be somewhat suspect by the time it arrives here.
 *
 * If this regex passes, then we can continue using it.
 * Otherwise (if it happens) we can ignore it
 */
class ValidTeamsMeetingUrl
{

    /**
     * Microsoft Teams meetings have this basic prefix
     *
     * This can be expanded to https://(.*).microsoft.com if they move meeting urls off the
     * teams.microsoft.com domain
     */
    private const MEETINGURL_PATTERN = 'https://teams.microsoft.com/';


    /**
     * Test whether $url is in the Microsoft Teams domain and in meeting url format
     *
     * @param string $url
     * @return false|int
     */
    public function isSatisifiedBy(string $url)
    {
        return stripos($url, self::MEETINGURL_PATTERN) === 0;
    }
}
