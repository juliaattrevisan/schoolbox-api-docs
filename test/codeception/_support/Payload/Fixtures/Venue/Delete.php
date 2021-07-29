<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\Venue;

use Payload\Request;
use Payload\PostTrait;

class Delete extends Request
{
    use PostTrait;

    private $venue;

    public function __construct(\Schoolbox\Fixtures\Venue\Model $venue)
    {
        $this->setReferer('/fixtures/venues');
        $this->venue = $venue;
    }

    public function getUrl(): string
    {
        return '/fixtures/venues/' . (string)($this->venue->getId()) . '/delete';
    }
}
