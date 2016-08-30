<?php
/**
 * Delegate class for logic that is common to both the dashboard and homepage
 * tile components.
 *
 * This would be a trait if Schoolbox was on PHP 5.4+, but it isn't yet so what
 * can I do?
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Component;

use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;
use \Schoolbox\SportsFixtures\Team\Player\Mapper as TeamPlayerMapper;
use \Schoolbox\SportsFixtures\Team\Player\Model as TeamPlayer;
use \Schoolbox\SportsFixtures\Match\Model as Match;
use \Schoolbox\SportsFixtures\Match\Mapper as MatchMapper;
use \Schoolbox\SportsFixtures\Result\Model as Result;
use \Schoolbox\SportsFixtures\Result\Mapper as ResultMapper;
use Schoolbox\Sportsfixtures\Team\Model as Team;
use Schoolbox\SportsFixtures\Team\Player\Participant\Mapper as TeamParticipantMapper;

class ComponentDelegate
{
    /**
     * @var TeamMapper
     */
    protected $teamMapper;

    /**
     * @var TeamPlayerMapper
     */
    protected $teamPlayerMapper;

    /**
     * @var TeamParticipantMapper
     */
    protected $teamParticipantMapper;

    /**
     * @var MatchMapper
     */
    protected $matchMapper;

    /**
     * @var ResultMapper
     */
    protected $resultMapper;

    /**
     * Delegate constructor.
     */
    public function __construct()
    {
        $this
            ->setTeamMapper(TeamMapper::getInstance())
            ->setTeamPlayerMapper(TeamPlayerMapper::getInstance())
            ->setTeamParticipantMapper(TeamParticipantMapper::getInstance())
            ->setMatchMapper(MatchMapper::getInstance())
            ->setResultMapper(ResultMapper::getInstance());
    }


    /**
     * Gets a collection of matches with only relevant teams and players attached, sorted by 'startAt'
     *
     * @param int[] $userIds
     * @param bool $getHistorical Whether to return matches in the past
     * @return Collection|Match[]
     */
    public function getMatchesForUsers(array $userIds, $getHistorical)
    {
        /** @var Collection|Match[] $teams */
        $matches = new Collection();

        if (!count($userIds)) {
            return $matches;
        }

        // Get all teams the users are currently in

        /** @var Collection|TeamPlayer[] $teamPlayers */
        $teamPlayers = $this->getTeamPlayerMapper()->findByColumn('user_id', $userIds);


        // Uniquify the teams, and ensure relevant players are in team player lists

        /** @var Collection|Team[] $teams */
        $teams = new Collection();
        foreach ($teamPlayers as $teamPlayer) {
            $team = $teamPlayer->getTeam();
            // Don't clobber teams
            if (!$teams[$team->getId()]) {
                $teams[$team->getId()] = $team;
            }
            // Add player to team, but prevent duplicates by inserting at collection index
            $teams[$team->getId()]->getPlayers()[$teamPlayer->getUser()->getId()] = $teamPlayer->getUser();
        }

        // Are we interested in matches in the past ?
        if ($getHistorical) {
            // Get all teams that the users played in
            $teamParticipants = $this->getTeamParticipantMapper()->findByColumn('user_id', $userIds);
            foreach ($teamParticipants as $teamParticipant) {
                $team = $teamParticipant->getTeam();
                if (!$teams[$team->getId()]) {
                    $teams[$team->getId()] = $team;
                }
                // Add player to team, but prevent duplicates by inserting at collection index
                $teams[$team->getId()]->getPlayers()[$teamParticipant->getUser()->getId()] = $teamParticipant->getUser();
            }
        }

        // Get all matches which involved these teams
        $home_matches = $this->getMatchMapper()->findByColumn('home_team_id', $teams->keys());
        $away_matches = $this->getMatchMapper()->findByColumn('away_team_id', $teams->keys());

        /** @var Collection|Match[] $all_matches */
        $all_matches = $home_matches->mergeUnique($away_matches, 'id');

        // Filter matches, and link teams which now contain relevant players
        foreach ($all_matches as $match) {
            if ($getHistorical || $match->getStartAt()->isFuture()) {
                $matches[$match->getId()] = $match;
            } else {
                continue;
            }

            // Can we link a home team ?
            if ($teams[$match->getHomeTeam()->getId()]) {
                $match->setHomeTeam($teams[$match->getHomeTeam()->getId()]);
            }

            // Can we link an away team ?
            if ($teams[$match->getAwayTeam()->getId()]) {
                $match->setAwayTeam($teams[$match->getAwayTeam()->getId()]);
            }
        }

        return $this->sortMatches($matches);
    }


    /**
     * @param int  $teamId
     * @param bool $includeHistorical Whether to also return matches that have been played
     * @return Collection|Match[]
     */
    public function getTeamMatches($teamId, $includeHistorical)
    {
        /** @var Collection|Match[] $matches */
        $matches = new Collection();
        if (!$teamId) {
            return $matches;
        }

        // Get the team we are looking at

        $team = $this->getTeamMapper()->findById($teamId);

        // Load all home and away matches for the team

        $home_matches = $this->getMatchMapper()->findByColumn('home_team_id', $team->getId());
        $away_matches = $this->getMatchMapper()->findByColumn('away_team_id', $team->getId());

        $all_matches = $home_matches->mergeUnique($away_matches, 'id');

        // Filter matches
        foreach ($all_matches as $match) {
            if ($includeHistorical || $match->getStartAt()->isFuture()) {
                $matches[$match->getId()] = $match;
            }
        }

        // Are we getting matches that have been played ?
        if ($includeHistorical) {
            $playedMatchIds = [];
            foreach ($matches as $match) {
                // Has the match been played ?
                if ($match->getStartAt()->isPast()) {
                    $playedMatchIds[] = $match->getId();
                }
            }
            /** @var Collection|Result[] $results */
            $results = $this->getResultMapper()->findByColumnList(['match_id' => $playedMatchIds]);

            foreach ($results as $result) {
                $matches[$result->getMatch()->getId()]->getResults()[$result->getTeam()->getId()] = $result;
            }
        }

        return $this->sortMatches($matches);
    }


    /**
     * Sort matches chronologically
     *
     * @param Collection|Match[] $matches
     * @return Collection|Match[]
     */
    public function sortMatches(Collection $matches)
    {
        // Sort matches by date order
        $matches->sortByCallback(function ($a, $b) {
            /** @var Match $a */
            /** @var Match $b */
            return ($a->getStartAt()->compare($b->getStartAt()));
        });

        return $matches;
    }

    /* Here be the getters/setters, nothing to see here ¯\_(ツ)_/¯
     */

    /**
     * @return TeamPlayerMapper
     */
    public function getTeamPlayerMapper()
    {
        return $this->teamPlayerMapper;
    }

    /**
     * @param  TeamPlayerMapper $teamPlayerMapper
     * @return static
     */
    public function setTeamPlayerMapper(TeamPlayerMapper $teamPlayerMapper)
    {
        $this->teamPlayerMapper = $teamPlayerMapper;
        return $this;
    }

    /**
     * @return TeamParticipantMapper
     */
    protected function getTeamParticipantMapper()
    {
        return $this->teamParticipantMapper;
    }

    /**
     * @param TeamParticipantMapper $teamParticipantMapper
     * @return static
     */
    protected function setTeamParticipantMapper(TeamParticipantMapper $teamParticipantMapper)
    {
        $this->teamParticipantMapper = $teamParticipantMapper;
        return $this;
    }

    /**
     * @return TeamMapper
     */
    public function getTeamMapper()
    {
        return $this->teamMapper;
    }

    /**
     * @param  TeamMapper $teamMapper
     * @return static
     */
    public function setTeamMapper(TeamMapper $teamMapper)
    {
        $this->teamMapper = $teamMapper;
        return $this;
    }

    /**
     * @return MatchMapper
     */
    public function getMatchMapper()
    {
        return $this->matchMapper;
    }

    /**
     * @param MatchMapper $matchMapper
     * @return static
     */
    public function setMatchMapper($matchMapper)
    {
        $this->matchMapper = $matchMapper;
        return $this;
    }

    /**
     * @return ResultMapper
     */
    public function getResultMapper()
    {
        return $this->resultMapper;
    }

    /**
     * @param ResultMapper $resultMapper
     * @return static
     */
    public function setResultMapper($resultMapper)
    {
        $this->resultMapper = $resultMapper;
        return $this;
    }
}