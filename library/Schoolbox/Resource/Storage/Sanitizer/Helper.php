<?php
/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Sanitizer;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;

/**
 * Helper for performing file sanitization.
 *
 * This wraps around the mimetype and filesystem checks which are required
 * before attempting to sanitize the file.
 */
class Helper
{
    /**
     * Constructor
     *
     * @param ISanitizer $sanitizer
     */
    public function __construct(private ISanitizer $sanitizer)
    {
    }

    /**
     * Sanitize file prior to storing.
     *
     * @param FilesystemInterface $filesystem The filesystem the file to
     *                                        sanitize exists on.
     * @param string              $path       The path to the file to sanitize.
     * @param string              $mimetype   The mimetype of the file to
     *                                        sanitize.
     * @return string|null Sanitized content if the item was sanitized, null if
     *                     it was not sanitized.
     */
    public function sanitize(
        FilesystemInterface $filesystem,
        string $path,
        string $mimetype,
    ): ?string {
        if (!$this->sanitizer->accepts($mimetype)) {
            return null;
        }
        try {
            $unsafeContent = $filesystem->read($path);
        } catch (FileNotFoundException $e) {
            return null;
        }
        $sanitizedContent = $this->sanitizer->sanitize($mimetype, $unsafeContent);
        if (!isset($sanitizedContent) || $unsafeContent === $sanitizedContent) {
            return null;
        }
        return $sanitizedContent;
    }
}
