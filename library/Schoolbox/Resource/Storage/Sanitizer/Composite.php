<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Sanitizer;

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
    public function sanitize(string $mimetype, string $contents): ?string
    {
        foreach ($this->sanitizers as $sanitizer) {
            $result = $sanitizer->sanitize($mimetype, $contents);
            if (isset($result)) {
                return $result;
            }
        }
        return null;
    }
}
