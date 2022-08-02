<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\HTMLPurifier\Adapter;

use Alaress\HTMLPurifier\IPurifier;

/**
 * A mock HTMLPurifier adapter: just returns the input for testing purposes.
 */
class MockAdapter implements IPurifier
{
    /**
     * @inheritdoc
     */
    public function purify(?string $input): ?string
    {
        return $input;
    }

    /**
     * @inheritdoc
     */
    public function purifyAbsoluteUri(?string $input, string $domain): ?string
    {
        return $input;
    }
}
