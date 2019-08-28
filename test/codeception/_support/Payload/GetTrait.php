<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload;

trait GetTrait
{
    public function getMethod(): string
    {
        return 'GET';
    }
}
