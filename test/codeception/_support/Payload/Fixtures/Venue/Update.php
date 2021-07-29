<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\Venue;

use Payload\Request;
use Payload\PostTrait;

class Update extends Request
{
    use PostTrait;

    private \Schoolbox\Fixtures\Venue\Model $venue;

    public function __construct(\Schoolbox\Fixtures\Venue\Model $venue)
    {
        $this->venue = $venue;

        $this->setReferer('/fixtures/venues/' . (string)($this->venue->getId()) . '/edit');

        $this->setFormParam('id', $this->venue->getId());
        $this->setFormParam('name', $venue->getName());
        $this->setFormParam('latitude', $venue->getLatitude());
        $this->setFormParam('longitude', $venue->getLongitude());
        $this->setFormParam('address', $venue->getAddress());
    }

    public function setName($newName)
    {
        $this->setFormParam('name', $newName);
    }

    public function getUrl(): string
    {
        return '/fixtures/venues/' . (string)($this->venue->getId());
    }
}
