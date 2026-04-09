<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec;

/**
 * Creates an arbitrary string that contains data which can
 * help uniquely identify a sis_event instance
 *
 * Note that it contains the (ttd, day, period code, classCode) which are
 * all required for the attendance link to work. The attendance
 * link is calculated at runtime for the currently logged-in user
 * and needs those four pieces to function as a path to the
 * remote TASS systems
 *
 * Also note that despite its name, this is not an RFC 9562 UUID.
 */
class Guid
{
    /**
     * An arbitrary char that nobody would use, to separate the pieces
     * If someone somehow uses this char you'll have to pick another
     */
    private const SEPARATOR = '|';

    /**
     * Note that Period Code is not the same as Period Description,
     * the latter being a nice human-readable string.
     *
     * Period Code is some internal identifier in tass
     *
     * @param string $startAt
     * @param string $ttid
     * @param string $day
     * @param string $periodCode
     * @param string $classCode
     * @return string
     */
    public function encode(
        string $startAt,
        string $ttid,
        string $day,
        string $periodCode,
        string $classCode
    ): string {
        return sprintf(
            '%s%s%s%s%s%s%s%s%s',
            $startAt,
            self::SEPARATOR,
            $ttid,
            self::SEPARATOR,
            $day,
            self::SEPARATOR,
            $periodCode,
            self::SEPARATOR,
            $classCode
        );
    }


    /**
     * Decodes an encoded guid
     *
     * @param string $guid
     * @return string[]
     */
    public function decode(string $guid): array
    {
        return explode(self::SEPARATOR, $guid);
    }
}
