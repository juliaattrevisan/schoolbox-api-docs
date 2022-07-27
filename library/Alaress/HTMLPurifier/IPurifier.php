<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\HTMLPurifier;

/**
 * Adapter interface for HTMLPurifier instances.
 *
 * Provided so that code which needs to use HTMLPurifier can be tested. Prior to
 * this the HTMLPurifier instance has always been accessed via a static method
 * `\Scrub::html`: this means that unit tests can't be viably written.
 */
interface IPurifier
{
    /**
     * Runs `$input` through a HTMLPurifier instance.
     *
     * @param string|null $input
     * @return string|null
     */
    public function purify(?string $input): ?string;

    /**
     * Runs `$input` through a HTMLPurifier instance, and converts relative
     * links within `$input` to absolute links to `$domain`.
     *
     * @param string|null $input
     * @param string      $domain
     * @return string|null
     */
    public function purifyAbsoluteUri(?string $input, string $domain): ?string;
}
