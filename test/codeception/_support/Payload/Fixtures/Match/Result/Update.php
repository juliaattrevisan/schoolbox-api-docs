<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\Match\Result;

use Payload\Request;
use Payload\PostTrait;
use Schoolbox\Fixtures\Result\Model;

class Update extends Request
{
    use PostTrait;

    private Model $result;

    public function __construct(Model $result)
    {
        $this->result = $result;

        $this->setReferer('/fixtures/results/' . $this->result->getId() . '/edit');

        $this->setFormParam('matchId', $this->result->getMatch()->getId());
        $this->setFormParam('teamId', $this->result->getTeam()->getId());
        $this->setFormParam('resultId', $this->result->getId());
        $this->setFormParam('statusId', 1);
        $this->setFormParam('description', '<p> This is an example of a result </p>');
    }

    public function getUrl(): string
    {
        return '/fixtures/results/' . $this->result->getId();
    }
}
