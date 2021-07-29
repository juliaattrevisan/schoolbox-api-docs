<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\Status;

use Payload\Request;
use Payload\PostTrait;

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
