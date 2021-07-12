<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Sanitizer;

use League\Flysystem\FilesystemInterface;

/**
 * Composite sanitizer: attempts to sanitize a file using all of the sanitizers
 * passed into the constructor in order.
 */
class Composite implements ISanitizer
{
    /**
     * @var array|ISanitizer[]
     */
    private array $sanitizers;

    /**
     * Constructor
     *
     * @param ISanitizer ...$sanitizers
     */
    public function __construct(ISanitizer ...$sanitizers)
    {
        $this->sanitizers = $sanitizers;
    }

    /**
     * @inheritdoc
     */
    public function accepts(string $mimetype): bool
    {
        foreach ($this->sanitizers as $sanitizer) {
            if ($sanitizer->accepts($mimetype)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function sanitizeBeforeStorage(FilesystemInterface $fileSystem, string $path, bool $dryRun = false): bool
    {
        foreach ($this->sanitizers as $sanitizer) {
            if ($sanitizer->sanitizeBeforeStorage($fileSystem, $path, $dryRun)) {
                return true;
            }
        }
        return false;
    }
}
