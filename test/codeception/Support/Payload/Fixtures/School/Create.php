<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Tests\Support\Payload\Fixtures\School;

use Tests\Support\Payload\Request;
use Tests\Support\Payload\PostTrait;

class Create extends Request
{
    use PostTrait;

    public function __construct(string $schoolName)
    {
        $this->setReferer('/fixtures/school');

        $this->setFormParam('entityId', '');
        $this->setFormParam('name', $schoolName);
    }

    public function getUrl(): string
    {
        return '/fixtures/school/add';
    }
}
