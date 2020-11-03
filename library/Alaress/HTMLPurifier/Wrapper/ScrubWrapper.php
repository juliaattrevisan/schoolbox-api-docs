<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\HTMLPurifier\Wrapper;

use Scrub;

/**
 * A simple wrapper around the HTMLPurifier instance used by `Scrub::html`.
 */
class ScrubWrapper implements IWrapper
{
    /**
     * @inheritdoc
     */
    public function purify(?string $input): ?string
    {
        return isset($input) ? Scrub::html($input) : null;
    }
}
