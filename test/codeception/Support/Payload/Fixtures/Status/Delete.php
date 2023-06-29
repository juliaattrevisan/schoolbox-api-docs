<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Tests\Support\Payload\Fixtures\Status;

use Tests\Support\Payload\Request;
use Tests\Support\Payload\PostTrait;

class Delete extends Request
{
    use PostTrait;

    private \Schoolbox\Fixtures\Status\Model $state;

    public function __construct(\Schoolbox\Fixtures\Status\Model $state)
    {
        $this->setReferer('/fixtures/statuses');
        $this->state = $state;
    }

    public function getUrl(): string
    {
        return '/fixtures/statuses/' . (string)($this->state->getId()) . '/delete';
    }
}
