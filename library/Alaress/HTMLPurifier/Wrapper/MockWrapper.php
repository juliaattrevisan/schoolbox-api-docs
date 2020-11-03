<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\HTMLPurifier\Wrapper;

/**
 * A mock HTMLPurifier wrapper: just returns the input for testing purposes.
 */
class MockWrapper implements IWrapper
{
    /**
     * @inheritdoc
     */
    public function purify(?string $input): ?string
    {
        return $input;
    }
}
