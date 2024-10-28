<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TokenReplacer\EscapeStrategy;

use Scrub;

/**
 * Escape strategy which escapes the value to be injected using `Scrub::html`
 * (for filtering unsafe HTML content).
 *
 * Note that escaping filters out unsafe content completely rather than escaping
 * it: unescaping cannot restore what was filtered out, so it simply returns the
 * escaped value.
 */
class ScrubHtml implements IEscapeStrategy
{
    /**
     * @param string $value
     * @return string
     */
    public function escape(string $value): string
    {
        return Scrub::html($value);
    }

    /**
     * @param string $value
     * @return string
     */
    public function unescape(string $value): string
    {
        return $value;
    }
}
