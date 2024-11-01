<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TokenReplacer\EscapeStrategy;

use Scrub;

/**
 * Escape strategy which escapes the value to be injected using `Scrub::text`
 * (for injecting unsafe text into HTML).
 */
class ScrubText implements IEscapeStrategy
{
    /**
     * @param string $value
     * @return string
     */
    public function escape(string $value): string
    {
        return Scrub::text($value);
    }

    /**
     * @param string $value
     * @return string
     */
    public function unescape(string $value): string
    {
        // Opposite of Scrub::text
        return html_entity_decode($value, ENT_IGNORE | ENT_QUOTES, 'UTF-8');
    }
}
