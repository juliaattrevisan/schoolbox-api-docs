<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Sanitizer;

use League\Flysystem\FilesystemInterface;

/**
 * Interface for classes which may sanitize files before storage.
 */
interface ISanitizer
{
    /**
     * Should this sanitizer attempt to sanitize files with this mimetype?
     *
     * @param string $mimetype
     * @return bool
     */
    public function accepts(string $mimetype): bool;

    /**
     * Sanitize a file before it is stored.
     *
     * @param FilesystemInterface $fileSystem Where does the file live?
     * @param string              $path       Path on `$filesystem` where this
     *                                        file lives
     * @param bool                $dryRun     If true: only pretend to sanitize
     *                                        the file
     * @return bool Was the file actually changed?
     */
    public function sanitizeBeforeStorage(FilesystemInterface $fileSystem, string $path, bool $dryRun = false): bool;
}
