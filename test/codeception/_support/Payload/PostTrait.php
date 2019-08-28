<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload;

trait PostTrait
{
    public function getMethod(): string
    {
        return 'POST';
    }
}
