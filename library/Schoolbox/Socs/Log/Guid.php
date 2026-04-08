<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Log;

/**
 * Generate a GUID to identify SOCS import runs uniquely.
 */
class Guid
{
    /**
     * Generate a Microsoft-style GUID string.
     */
    public function generate(): string
    {
        $uuid = md5(uniqid((string)mt_rand(), true));

        return substr($uuid, 0, 8) . '-' .
            substr($uuid, 8, 4) . '-' .
            substr($uuid, 12, 4) . '-' .
            substr($uuid, 16, 4) . '-' .
            substr($uuid, 20, 12);
    }
}
