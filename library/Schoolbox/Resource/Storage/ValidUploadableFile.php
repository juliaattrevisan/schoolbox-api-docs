<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage;

use Alaress\Specification\Base as BaseSpecification;
use Alaress_Byte_Format;
use Alaress_Date_Format;
use Schoolbox\File\Exception as FileException;
use Schoolbox\File\Mimetype;

/**
 * File upload validation, extracted to a spec pattern.
 *
 * This validation may be bypassed if:
 * * the file is received via a CLI (where there is no user context)
 * * the current user is a superuser
 */
class ValidUploadableFile extends BaseSpecification
{
    /**
     * The maximum number of days in which a file may expire, and be allowed to
     * bypass the maximum file size restriction.
     *
     * Set to 15 currently, as most temp file upload locations set an expiry two
     * weeks into the future.
     */
    private const MAX_DAYS_ALLOWED_FOR_TEMP_FILE_BYPASS = 15;

    /**
     * Constructor
     *
     * @param Alaress_Date_Format $now The current time: must be pre-defined for
     *                                 comparing a known stable time against the
     *                                 file's expiry.
     */
    public function __construct(private Alaress_Date_Format $now)
    {
    }

    /**
     * Validate that `$candidate` is uploadable:
     * * it must have a mimetype
     * * its mimetype must be allowed
     * * if it has no expiry, or an expiry after 15 days from now:
     *   its size must not be greater than the mimetype's max allowable size
     *
     * @param Model $candidate
     * @return true always true if file is uploadable
     * @throws FileException if `$candidate` wasn't uploadable for whatever
     *                       reason
     */
    public function isSatisfiedBy($candidate): bool
    {
        $fileMime    = $candidate->getMimetype();
        $errorSuffix = "Try a different file or contact your IT administrator.";

        // Assert: Mime set
        if (!$fileMime instanceof Mimetype) {
            throw new FileException(
                "This file is not a permitted file type. $errorSuffix",
                FileException::CODE_MIMETYPE_NOT_FOUND
            );
        }

        // Assert: Mime type allowed
        if (!$fileMime->isEnabled() || $fileMime->isDeleted()) {
            throw new FileException(
                "This file is not a permitted file type. $errorSuffix",
                FileException::CODE_MIMETYPE_NOT_ALLOWED
            );
        }

        // Assert: Acceptable size
        // (bypass if this file is due to expire within a reasonable time frame)
        $fileExpiry = $candidate->getExpiryAt();
        $assertSize = true;
        if ($fileExpiry) {
            $latestTempDate = (clone $this->now)->addDays(self::MAX_DAYS_ALLOWED_FOR_TEMP_FILE_BYPASS);
            if ($fileExpiry->lte($latestTempDate)) {
                $assertSize = false;
            }
        }
        if ($assertSize) {
            if ($fileMime->getActualMaxFileSize() < $candidate->getSize()) {
                throw new FileException(
                    sprintf(
                        "This file exceeds the file size restriction of %s. %s",
                        Alaress_Byte_Format::bytesToString($fileMime->getActualMaxFileSize()),
                        $errorSuffix,
                    ),
                );
            }
        }
        return true;
    }
}
