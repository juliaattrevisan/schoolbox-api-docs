<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\Division;

use Payload\Request;
use Payload\PostTrait;

class SetPosition extends Request
{
    use PostTrait;

    private \Schoolbox\Fixtures\Team\Model $team;

    public function __construct(\Schoolbox\Fixtures\Team\Model $team, \Schoolbox\User $user, \Schoolbox\Fixtures\Position\Model $position)
    {
        $this->team = $team;
        $this->setReferer('/fixtures/divisions/' . $this->team->getDivision()->getId() . '/lobby');

        $this->setFormParam('team_id', $team->getId());
        $this->setFormParam('user_id', $user->getId());
        $this->setFormParam('positions[]', $position->getId());
    }

    public function getUrl(): string
    {
        return '/fixtures/divisions/' . $this->team->getDivision()->getId() . '/setPositions';
    }
}
