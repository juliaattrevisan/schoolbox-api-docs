<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Payload;

trait DeleteTrait
{
    public function getMethod(): string
    {
        return 'DELETE';
    }
}
