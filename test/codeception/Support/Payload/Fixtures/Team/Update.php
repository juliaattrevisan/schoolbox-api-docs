<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Tests\Support\Payload\Fixtures\Team;

use Tests\Support\Payload\Request;
use Tests\Support\Payload\PostTrait;

class Update extends Request
{
    use PostTrait;

    private \Schoolbox\Fixtures\Team\Model $team;

    public function __construct(\Schoolbox\Fixtures\Team\Model $team)
    {
        $this->team = $team;
        $this->setReferer('/fixtures/teams/' . $this->team->getId() . '/edit');

        $this->setFormParam('teamId', $this->team->getId());
        $this->setFormParam('divisionId', $this->team->getDivision()->getId());
        $this->setFormParam('name', $this->team->getName());
        #$this->setFormParam('coach[]', '');
    }

    public function getUrl(): string
    {
        return '/fixtures/teams/' . $this->team->getId();
    }
}
