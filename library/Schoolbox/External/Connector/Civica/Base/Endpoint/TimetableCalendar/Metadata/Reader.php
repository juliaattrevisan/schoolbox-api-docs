<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint\TimetableCalendar\Metadata;

use Schoolbox\External\Connector\Civica\Base\Endpoint\Codec;

class Reader
{
    /**
     * Extract the attendance url from the metdata
     *
     * @param array|string $metadata
     * @return string|null
     * @throws \JsonException
     */
    public function getAttendanceLink(array|string $metadata): ?string
    {
        $attendanceUrl = (new Codec\Metadata())->decode($metadata)['links']['attendance'] ?? null;

        if (null === $attendanceUrl) {
            return null;
        }

        /* Slap it into a format that our iframer can use
         * I don't have access to a $router here, so I guess we have to harcode it for now
         */
        return sprintf(
            '/iframe/civica/attendance/%s',
            base64_encode($attendanceUrl)
        );
    }
}
