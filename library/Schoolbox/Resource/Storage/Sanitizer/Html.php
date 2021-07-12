<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Sanitizer;

use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use enshrined\svgSanitize\Sanitizer;

/**
 * Sanitizer for HTML files.
 */
class Html implements ISanitizer
{
    /**
     * Which mime types does this sanitizer accept?
     */
    private const ACCEPTED_MIMETYPE = 'text/html';

    /**
     * The internal sanitizer this class uses to actually sanitize HTML.
     */
    private ScrubAdapter $scrubAdapter;

    /**
     * Constructor
     *
     * @param ScrubAdapter $scrubAdapter
     */
    public function __construct(ScrubAdapter $scrubAdapter)
    {
        $this->scrubAdapter = $scrubAdapter;
    }

    /**
     * @inheritdoc
     */
    public function sanitize(string $mimetype, string $contents): ?string
    {
        if ($mimetype !== self::ACCEPTED_MIMETYPE) {
            return null;
        }
        return $this->scrubAdapter->purify($contents);
    }
}
