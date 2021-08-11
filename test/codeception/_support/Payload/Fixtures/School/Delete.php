<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\School;

use Payload\Request;
use Payload\PostTrait;

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
