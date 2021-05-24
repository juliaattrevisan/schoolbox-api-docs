<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Notif\Formatter;

use ErrorException;
use Monolog\Formatter\FormatterInterface;

class CompressedFormatter implements FormatterInterface
{

    const COMPRESSION_LEVEL = 9; // 9 is max, 6 is default, -1 is none

    /**
     * Formats a log record.
     *
     * @param  array $record A record to format
     * @return mixed The formatted record
     */
    public function format(array $record)
    {
        return self::compress($record);
    }

    /**
     * Formats a set of log records.
     *
     * @param  array $records A set of records to format
     * @return mixed The formatted set of records
     */
    public function formatBatch(array $records)
    {
        return $this->format($records);
    }


    /**
     * @param mixed $data
     * @return string
     */
    public static function compress($data)
    {
        // serialize the message contents
        $uncompressed = serialize($data);

        // compress the resultant string
        $compressed = gzcompress($uncompressed, self::COMPRESSION_LEVEL);

        // base64 encode so it survives transport
        $encoded = base64_encode($compressed);

        // return the result
        return $encoded;
    }


    /**
     * @param string $data
     * @throws CompressionException
     * @return mixed
     */
    public static function uncompress($data)
    {
        try {
            // base64-decode the data
            if (($decoded = base64_decode($data)) === false) {
                throw new CompressionException("Could not decode data");
            }

            // uncompress the resultant binary
            if (($uncompressed = gzuncompress($decoded)) === false) {
                throw new CompressionException("Could not uncompress data");
            }

            // unserialize the resultant string
            $unserialized = unserialize($uncompressed);

            // return the result
            return $unserialized;
        } catch (ErrorException $e) {
            // cast error exceptions to compression exceptions
            throw new CompressionException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
