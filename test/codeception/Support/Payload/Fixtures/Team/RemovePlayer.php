<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Tests\Support\Payload\Fixtures\Team;

use Tests\Support\Payload\Request;
use Tests\Support\Payload\PostTrait;

class RemovePlayer extends Request
{
    use PostTrait;

    private $team;

    public function __construct(\Schoolbox\Fixtures\Team\Model $team, \Schoolbox\User $user)
    {
        $this->team = $team;
        $this->setReferer('/fixtures/divisions/' . $this->team->getDivision()->getId() . '/lobby');

        $this->setFormParam('userId[]', $user->getId());
    }

    public function getUrl(): string
    {
        return '/fixtures/teams/' . $this->team->getId() . '/removePlayers';
    }
}
