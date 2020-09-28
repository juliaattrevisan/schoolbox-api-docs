<?php
/*
 * https://vagrant.dev.schoolbox.cloud/fixtures/matches/
 * Referer https://vagrant.dev.schoolbox.cloud/fixtures/divisions/21/matches/new?season=29
 * 
 * divisionId	21
matchId	
name	Its+a+name
seasonId	29
startAtDate	30/09/2020+8:17am
venueId	11
homeTeamId	school:8
awayTeamId	school:14
itinerary[matchId][]	[…]
0	
1	
2	
itinerary[itemId][]	[…]
0	
1	
2	
itinerary[date][]	[…]
0	29/09/2020+8:17am
1	
2	
itinerary[description][]	[…]
0	stuff
1	
2	
notes	<p>afagagrargrag</p> 
*/

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

class Create extends Request
{
    use PostTrait;
    
    public function __construct(string $matchName, Division\Model $division, Venue\Model $venue, Team\Model $homeTeam, Team\Model $awayTeam)
    {
        $this->setReferer('/fixtures/divisions/' . $division->getId() . '/matches/new?season=' . $division->getSeason());
        
        $this->setFormParam('divisionId', $division->getId());
        $this->setFormParam('matchId', '');
        $this->setFormParam('seasonId', $division->getSeason());
        $this->setFormParam('name', $matchName);
        
    }
    
    public function getUrl():string
    {
        return '/fixtures/matches/';
    }
}