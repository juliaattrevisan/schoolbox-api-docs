<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Sanitizer;

use enshrined\svgSanitize\Sanitizer;

/**
 * Sanitizer for SVG files.
 */
class Svg implements ISanitizer
{
    /**
     * Which mime types does this sanitizer accept?
     */
    private const ACCEPTED_MIMETYPE = 'image/svg+xml';

    /**
     * The internal sanitizer this class uses to actually sanitize a SVG.
     */
    private Sanitizer $sanitizer;

    /**
     * Constructor
     *
     * @param Sanitizer $sanitizer
     */
    public function __construct(Sanitizer $sanitizer)
    {
        $this->sanitizer = $sanitizer;
    }

    /**
     * @inheritdoc
     */
    public function sanitize(string $mimetype, string $contents): ?string
    {
        if ($mimetype !== self::ACCEPTED_MIMETYPE) {
            return null;
        }
        return $this->sanitizer->sanitize($contents);
    }
}
