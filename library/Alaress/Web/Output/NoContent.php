<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Output;

/**
 * Used when we wish for the response to have no body
 *
 * Note that 200 should always contain the entity (GET) or action result (POST/PUT etc).
 *
 * Expected to use this output for 204, 205, 3xx etc
 */
class NoContent extends Base
{

    /**
     * @inheritdoc
     */
    protected function outputResponseBody(): void
    {
        // noop
    }
}
