<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\HTMLPurifier\Adapter;

use Alaress\HTMLPurifier\IPurifier;
use Scrub;

/**
 * A simple adapter around the HTMLPurifier instance used by `Scrub::html`.
 */
class ScrubAdapter implements IPurifier
{
    /**
     * @inheritdoc
     */
    public function purify(?string $input): ?string
    {
        return isset($input) ? Scrub::html($input) : null;
    }

    /**
     * @inheritdoc
     */
    public function purifyAbsoluteUri(?string $input, string $domain): ?string
    {
        return isset($input) ? Scrub::htmlAbsoluteUri($input, $domain) : null;
    }
}
