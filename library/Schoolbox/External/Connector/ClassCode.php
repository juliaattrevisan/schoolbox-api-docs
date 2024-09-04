<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector;

use Psr\Log\LoggerInterface;

/**
 * This class forcibly crushes an arbitrary class code from a sis/third party
 * into a length and format that Schoolbox requires:
 *
 *  - trimmed string
 *  - less than 64 char
 */
class ClassCode
{
    /**
     * sis_class.code
     */
    private const MAXLENGTH_CLASS_CODE = 64;


    /**
     * Constructor
     *
     * @param LoggerInterface|null $logger
     */
    public function __construct(private ?LoggerInterface $logger)
    {
    }


    /**
     * Takes a mixed input and coerces the heck out of it
     *
     * @param mixed $input
     * @return string
     */
    public function generate($input): string
    {
        /* Ditch any useless cruft
         */
        $classCode = trim((string)$input);

        /* Forcibly truncate it at 64 chars, which is our current schema definition
         * for sis_class.code. There's a desync between sis_class.code (64) and
         * sis_event.code (255) at the moment.
         */
        $truncated = mb_substr($classCode, 0, self::MAXLENGTH_CLASS_CODE);

        /* Throw a warning if the truncated version is different than the one we started
         * with; that's an indication that we just lost data (arguably that data is wrong,
         * and needs to be fixed by the schools, but whatever)
         */
        if ($truncated !== $classCode) {
            $this->logger?->warning(sprintf(
                'Class Code truncation: was "%s", but shortened to "%s".',
                $classCode,
                $truncated
            ));
        }

        return $truncated;
    }
}
