<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\Matchup;

use Payload\Request;
use Payload\PostTrait;
use Schoolbox\Fixtures\Matchup\Model;

class Delete extends Request
{
    use PostTrait;

    private Model $match;

    public function __construct(Model $match)
    {
        $this->match = $match;

        $this->setReferer('/fixtures/divisions/' . $this->match->getDivision()->getId() . '/matches');
    }

    public function getUrl(): string
    {
        return '/fixtures/matches/' . $this->match->getId() . '/delete';
    }
}
