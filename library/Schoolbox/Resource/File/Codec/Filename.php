<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.education>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 */

declare(strict_types=1);

namespace Schoolbox\Resource\File\Codec;

use InvalidArgumentException;

class Filename
{
    /**
     * Most of our varchar fields are 255 chars long, so I guess that's a sane default presumption
     */
    private const int DEFAULT_MAXLEN = 255;


    /**
     * The three biggest operating systems (windows, macos, linux) suggest 255 is the maximum
     */
    private const int OS_MAX = 255;


    /**
     * Adopt a generic period-separated string but you could make this a parameter of generate() if you have some
     * weird circumstance where there's a special format
     */
    private const string FILENAME_FORMAT = '%s.%s';


    /**
     * Character encoding is utf-8 by default but you can choose
     */
    private const string CHARACTER_ENCODING = 'UTF-8';


    /**
     * Construct a multibyte-friendly forcibly-truncated filename according to maxlength
     * The extension does not count toward the filename length.
     *
     * @param string|null $basename
     * @param string|null $extension
     * @param int $maxlength
     * @param string $characterEncoding
     * @return string
     */
    public function generate(
        ?string $basename,
        ?string $extension,
        int $maxlength = self::DEFAULT_MAXLEN,
        string $characterEncoding = self::CHARACTER_ENCODING
    ): string {
        /* Must be a natural number
         */
        if (0 >= $maxlength) {
            throw new InvalidArgumentException('Invalid maximum length: must be greater than zero');
        }

        /* afaict this is the largest for the three big filesystems but I may be wrong:
         * Linux systems might be limited to ~80 or so
         */
        if (self::OS_MAX < $maxlength) {
            throw new InvalidArgumentException(sprintf(
                'Invalid maximum length: filename must be less than or equal to %s',
                self::OS_MAX
            ));
        }

        /* Mutate this basename to the minimum by truncating whitespace and unprintable chars from both ends
         * Forcibly minimise to $maxlength
         */
        $updatedBasename = trim(mb_substr(trim((string)$basename), 0, $maxlength, $characterEncoding));

        /* If the filename doesn't have a length now, that's a problem
         */
        if (0 === strlen($updatedBasename)) {
            throw new InvalidArgumentException('Filename does not contain a meaningful value');
        }

        /* The extension should also be meaningful for 99% of the usages
         */
        $updatedExtension = trim((string)$extension);

        /* If the extension doesn't have a length now, that's a problem
         */
        if (0 === strlen($updatedExtension)) {
            throw new InvalidArgumentException('Extension does not contain a meaningful value');
        }

        /* Construct a filename
         */
        return sprintf(
            self::FILENAME_FORMAT,
            $updatedBasename,
            $updatedExtension
        );
    }
}
