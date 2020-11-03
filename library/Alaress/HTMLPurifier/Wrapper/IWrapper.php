<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\HTMLPurifier\Wrapper;

/**
 * Interface for wrappers around HTMLPurifier instances.
 *
 * Provided so that code which needs to use HTMLPurifier can be tested. Prior to
 * this the HTMLPurifier instance has always been accessed via a static method
 * `\Scrub::html`: this means that unit tests can't be viably written.
 */
interface IWrapper
{
    /**
     * Runs `$input` through a HTMLPurifier instance.
     *
     * @param string $input
     * @return string
     */
    public function purify(string $input): string;
}
