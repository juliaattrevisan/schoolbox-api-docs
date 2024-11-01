<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TokenReplacer\EscapeStrategy;

/**
 * "Escape" strategy which doesn't escape the value to be injected at all:
 * it simply returns the value as-is.
 */
class None implements IEscapeStrategy
{
    /**
     * @param string $value
     * @return string
     */
    public function escape(string $value): string
    {
        return $value;
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
