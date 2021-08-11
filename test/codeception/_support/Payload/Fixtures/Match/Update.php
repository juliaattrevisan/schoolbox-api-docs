<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\Match;

use Payload\Request;
use Payload\PostTrait;

class Update extends Request
{
    use PostTrait;

    private \Schoolbox\Fixtures\Match\Model $match;

    public function __construct(\Schoolbox\Fixtures\Match\Model $match)
    {
        $this->match = $match;

        $this->setReferer('/fixtures/matches/' . $this->match->getId() . '/edit');

        $itinerary = $this->match->getItinerary();

        $matchTimestamp = time();

        $this->setFormParam('divisionId', $this->match->getDivision()->getId());
        $this->setFormParam('matchId', $this->match->getId());
        $this->setFormParam('seasonId', $this->match->getSeason()->getId());
        $this->setFormParam('name', $this->match->getName());
        $this->setFormParam('startAtDate', date('d/m/Y g:ia', strtotime('+ 4 day', $matchTimestamp)));
        $this->setFormParam('venueId', $this->match->getVenue()->getId());
        $this->setFormParam('homeTeamId', $this->match->getHomeTeam()->getId());
        $this->setFormParam('awayTeamId', 'school:' . $this->match->getAwaySchool()->getId());
        $this->setFormParam('itinerary[matchId][0]', $this->match->getId());
        $this->setFormParam('itinerary[itemId][0]', $itinerary->first()->getId());
        $this->setFormParam('itinerary[date][0]', date('d/m/Y g:ia', strtotime('+ 3 day', $matchTimestamp)));
        $this->setFormParam('itinerary[description][0]', $itinerary->first()->getDescription());
        $this->setFormParam('notes', $this->match->getNotes());
    }

    public function getUrl(): string
    {
        return '/fixtures/matches/' . $this->match->getId();
    }
}
