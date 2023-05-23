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
     * Constructor
     *
     * @param Alaress_Date_Format $latestTempDate The latest possible expiry date at which the file being uploaded is
     *                                            considered a temp file, and thus bypasses size validation.
     */
    public function __construct(
        private Alaress_Date_Format $latestTempDate,
    ) {
    }

    /**
     * Validate that `$candidate` is uploadable:
     * * it must have a mimetype
     * * its mimetype must be allowed
     * * if it has no expiry, or an expiry after `$this->latestTempDate`:
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
                "This file has no detectable type. $errorSuffix",
                FileException::CODE_MIMETYPE_NOT_FOUND
            );
        }

        // Assert: Mime type allowed
        if (!$fileMime->isEnabled() || $fileMime->isDeleted()) {
            throw new FileException(
                "This file is not a valid file type. $errorSuffix",
                FileException::CODE_MIMETYPE_NOT_ALLOWED
            );
        }

        // Assert: Acceptable size
        // (bypass if this file is due to expire within a reasonable time frame)
        $fileExpiry = $candidate->getExpiryAt();
        $assertSize = true;
        if ($fileExpiry) {
            if ($fileExpiry->lte($this->latestTempDate)) {
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
