<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\Match\Result;

use Payload\Request;
use Payload\PostTrait;
use Schoolbox\Fixtures\Match\Model;

class Create extends Request
{
    use PostTrait;

    private Model $match;
    private \Schoolbox\Fixtures\Team\Model $team;

    public function __construct(Model $match, \Schoolbox\Fixtures\Team\Model $team)
    {
        $this->match = $match;
        $this->team = $team;

        $this->setReferer('/fixtures/matches/' . $this->match->getId() . '/results/' . $this->team->getId() . '/new');

        $this->setFormParam('matchId', $this->match->getId());
        $this->setFormParam('teamId', $this->team->getId());
        $this->setFormParam('resultId', '');
        $this->setFormParam('statusId', 1);
        $this->setFormParam('description', '<p> This is an example of a result </p>');
    }

    public function getUrl(): string
    {
        return '/fixtures/match/' . $this->match->getId() . '/team/' . $this->team->getId() . '/results';
    }
}
