<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\Division;

use Payload\Request;
use Payload\PostTrait;
use Schoolbox\Fixtures\Sport\Model;

class Create extends Request
{
    use PostTrait;

    private Model $sport;
    private \Schoolbox\Fixtures\Season\Model $season;

    public function __construct(string $divisionName, Model $sport, \Schoolbox\Fixtures\Season\Model $season)
    {
        $this->sport = $sport;
        $this->season = $season;
        $this->setReferer('/fixtures/sports/' . $this->sport->getId() . '/divisions/new');

        $this->setFormParam('sportId', $sport->getId());
        $this->setFormParam('divisionId', '');
        $this->setFormParam('name', $divisionName);
        $this->setFormParam('seasonId', $this->season->getId());
        $this->setFormParam('url', 'http://www.example.com');
    }

    public function getUrl(): string
    {
        return '/fixtures/divisions/';
    }
}
