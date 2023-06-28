<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Tests\Support\Payload\Fixtures\Venue;

use Tests\Support\Payload\Request;
use Tests\Support\Payload\PostTrait;

class Create extends Request
{
    use PostTrait;

    public function __construct(string $venueName)
    {
        $this->setReferer('/fixtures/venues/new');
        $this->setFormParam('id', '');
        $this->setFormParam('name', $venueName);
        $this->setFormParam('latitude', '');
        $this->setFormParam('longitude', '');
        $this->setFormParam('address', '9 someplace road, somearea, VIC');
    }

    public function getUrl(): string
    {
        return '/fixtures/venues/';
    }
}
