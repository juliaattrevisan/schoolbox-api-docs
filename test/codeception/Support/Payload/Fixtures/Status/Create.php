<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Tests\Support\Payload\Fixtures\Status;

use Tests\Support\Payload\Request;
use Tests\Support\Payload\PostTrait;

class Create extends Request
{
    use PostTrait;

    public function __construct(string $statusName)
    {
        $this->setReferer('/fixtures/statuses');
        $this->setFormParam('entityId', '');
        $this->setFormParam('name', $statusName);
    }

    public function getUrl(): string
    {
        return '/fixtures/statuses/';
    }
}
