<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Tests\Support\Payload\Fixtures\Team;

use Tests\Support\Payload\Request;
use Tests\Support\Payload\PostTrait;

class Create extends Request
{
    use PostTrait;

    private \Schoolbox\Fixtures\Division\Model $division;

    public function __construct(string $name, \Schoolbox\Fixtures\Division\Model $division)
    {
        $this->division = $division;
        $this->setReferer('/fixtures/divisions/' . $this->division->getId() . '/teams/new');

        $this->setFormParam('divisionId', $this->division->getId());
        $this->setFormParam('teamId', '');
        $this->setFormParam('name', $name);
        $this->setFormParam('url', 'https://www.example.com');
        #$this->setFormParam('coach[]', '');
    }

    public function getUrl(): string
    {
        return '/fixtures/teams/';
    }
}
