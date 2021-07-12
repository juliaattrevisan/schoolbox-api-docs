<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Sanitizer;

/**
 * Interface for classes which may sanitize files before storage.
 */
interface ISanitizer
{
    /**
     * Sanitize a file.
     *
     * @param string $mimetype The file's mimetype: required to determine if
     *                         this sanitizer can actually sanitize this file
     * @param string $contents The file's contents
     * @return string|null     Sanitized content, or null if this sanitizer does
     *                         not accept files of type `$mimetype`
     */
    public function sanitize(string $mimetype, string $contents): ?string;
}
