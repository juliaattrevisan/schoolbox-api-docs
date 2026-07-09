<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Edlink\Base\Endpoint\Profile;

/**
 * Decides whether a person's photo needs (re)downloading.
 *
 * Edlink exposes no photo-specific modified date, ETag or version, so we gate
 * on the person-level updated_date: only fetch the photo when the source record
 * changed after we last stored it. This avoids re-downloading every person's
 * photo on every sync.
 */
class PhotoFreshness
{
    /**
     * @param \Alaress_Date_Format|null $storedAt        When the currently stored portrait was saved
     *                                                   (Storage uploaded_on), or null if none stored.
     * @param \Alaress_Date_Format|null $sourceUpdatedAt The Edlink person's updated_date, or null if
     *                                                   absent/unparseable.
     */
    public static function needsDownload(
        ?\Alaress_Date_Format $storedAt,
        ?\Alaress_Date_Format $sourceUpdatedAt
    ): bool {
        /* Never stored a photo yet — fetch it.
         */
        if (null === $storedAt) {
            return true;
        }

        /* No usable source timestamp — we can't prove the photo is unchanged, so
         * fetch it. This preserves the previous always-download behaviour when
         * Edlink omits updated_date.
         */
        if (null === $sourceUpdatedAt) {
            return true;
        }

        /* Only fetch when the source record changed after we stored the photo.
         */
        return $storedAt->lt($sourceUpdatedAt);
    }
}
