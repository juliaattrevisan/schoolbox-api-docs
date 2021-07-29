<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\Match;

use Payload\Request;
use Payload\PostTrait;
use Schoolbox\Fixtures\Division;
use Schoolbox\Fixtures\Venue;
use Schoolbox\Fixtures\Team;
use Schoolbox\Fixtures\School;

class Create extends Request
{
    use PostTrait;

    public function __construct(string $matchName, Division\Model $division, Venue\Model $venue, Team\Model $homeTeam, School\Model $awayTeam)
    {
        $this->setReferer('/fixtures/divisions/' . $division->getId() . '/matches/new?season=' . $division->getSeason()->getId());

        $matchTimestamp = time();

        $this->setFormParam('divisionId', $division->getId());
        $this->setFormParam('matchId', '');
        $this->setFormParam('seasonId', $division->getSeason()->getId());
        $this->setFormParam('name', $matchName);
        $this->setFormParam('startAtDate', date('d/m/Y g:ia', strtotime('+ 3 day', $matchTimestamp)));
        $this->setFormParam('venueId', $venue->getId());
        $this->setFormParam('homeTeamId', $homeTeam->getId());
        $this->setFormParam('awayTeamId', 'school:' . $awayTeam->getId());
        $this->setFormParam('itinerary[matchId][0]', '');
        $this->setFormParam('itinerary[itemId][0]', '');
        $this->setFormParam('itinerary[date][0]', date('d/m/Y g:ia', strtotime('+ 2 day', $matchTimestamp)));
        $this->setFormParam('itinerary[description][0]', 'Preperation of the grounds');
        $this->setFormParam('notes', 'The notes about the match');
    }

    public function getUrl(): string
    {
        return '/fixtures/matches/';
    }
}
