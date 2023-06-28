<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Tests\Support\Payload\Fixtures\School;

use Tests\Support\Payload\Request;
use Tests\Support\Payload\PostTrait;

class Delete extends Request
{
    use PostTrait;

    private \Schoolbox\Fixtures\School\Model $school;

    public function __construct(\Schoolbox\Fixtures\School\Model $school)
    {
        $this->setReferer('/fixtures/school');
        $this->school = $school;
    }

    public function getUrl(): string
    {
        return '/fixtures/school/' . (string)($this->school->getId()) . '/delete';
    }
}
