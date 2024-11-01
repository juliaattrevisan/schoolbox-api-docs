<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TokenReplacer\EscapeStrategy;

/**
 * Escape and unescape strategy used by the token replacer.
 */
interface IEscapeStrategy
{
    /**
     * Escapes a token value, for when the token is to be injected into a
     * template.
     *
     * @param string $value
     * @return string
     */
    public function escape(string $value): string;

    /**
     * Unescapes a token value, for when attempting to extract a token value out
     * of a template.
     *
     * @param string $value
     * @return string
     */
    public function unescape(string $value): string;
}
