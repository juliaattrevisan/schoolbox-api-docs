<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar\Codec;

use Schoolbox\External\Connector\Liss\Base\Endpoint\Factory;

class PayloadFilename
{
    /**
     * This char separates the suds source, liss method, liss source and datetime
     * in a liss payload's filename.
     */
    public const SEPARATOR = '-';


    /**
     * Takes the params and encodes them into a filename that appears in the storage table
     *
     * This format is as follows:
     *     suds_liss
     *     liss method (e.g. belltimes, classmemberships, etc)
     *     source name (if provided by client; otherwise, "default")
     *     date
     *
     * @param string $lissMethodName
     * @param string $sourceName
     * @return string
     */
    public function encode(
        string $lissMethodName,
        string $sourceName,
    ): string {
        /* This $sourceName must not contain any special characters, such as those used by our filename
         * encoding scheme, cos it'll snap if you do.
         */
        $payloadSourceName = str_replace(self::SEPARATOR, '', $sourceName);

        /* The date this file was made (should correlate to when it was pushed to us)
         */
        $now = \Alaress_Date_Format::now();

        return sprintf(
            '%s%s%s%s%s%s%s.log',
            Factory::SOURCE_NAME,
            self::SEPARATOR,
            $lissMethodName,
            self::SEPARATOR,
            $payloadSourceName,
            self::SEPARATOR,
            $now->getUsingFormat('Ymd_His')
        );
    }


    /**
     * Takes a liss payload's filename and snaps it into its constituent parts
     *
     * @param string $filename
     * @return array
     */
    public function decode(string $filename): array
    {
        $parts = explode(self::SEPARATOR, $filename);

        return [
            /* suds_liss, usually - this shouldn't really change or be anything else
             */
            'source' => $parts[0],
            /* The liss method that spawned a payload
             */
            'lissMethodName' => $parts[1],
            /* "default", normally
             *
             * ...unless the school specified a ?source parameter in their Receiver all,
             * in which case it'll be that. Redlands used it.
             */
            'sourceName' => $parts[2],
            /* The date it was pushed to us
             */
            'date' => $parts[3]
        ];
    }
}
