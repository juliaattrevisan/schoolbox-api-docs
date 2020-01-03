<?php
/**
 * Delegate class for logic that is common to both the dashboard and homepage
 * tile components.
 *
 * This would be a trait if Schoolbox was on PHP 5.4+, but it isn't yet so what
 * can I do?
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Component;

use Alaress\Database\Connector\Base;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use \Schoolbox\Fixtures\Team\Player\Mapper as TeamPlayerMapper;
use \Schoolbox\Fixtures\Match\Model as Match;
use \Schoolbox\Fixtures\Match\Mapper as MatchMapper;
use \Schoolbox\Fixtures\Result\Model as Result;
use \Schoolbox\Fixtures\Result\Mapper as ResultMapper;
use Schoolbox\Fixtures\Team\Player\Participant\Mapper as TeamParticipantMapper;
use Alaress\Database\Predicate\Builder;
use UnexpectedValueException;

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
     * @var Base
     */
    protected $database;

    /**
     * Delegate constructor.
     *
     * @var Base $database
     */
    public function __construct(Base $database)
    {
        $this->database = $database;

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
     * @param array $userIds
     * @param bool  $includeHistorical Whether to return matches in the past
     * @return Collection|Match[]
     */
    public function getMatchesForUsers(array $userIds, $includeHistorical)
    {
        $historical = '';
        if (!$includeHistorical) {
            $historical = "AND DATE(sportsfixtures_match.start_at) >= DATE(NOW())";
        }

        $placeholders = Builder::getArrayPlaceholders($userIds, 'userId');

        $query = "
            SELECT DISTINCT     sportsfixtures_match.id
            FROM                sportsfixtures_match
            LEFT JOIN           sportsfixtures_team_participantcoach ON
                                sportsfixtures_match.id = sportsfixtures_team_participantcoach.match_id AND
                                sportsfixtures_team_participantcoach.user_id IN {$placeholders} AND
                                sportsfixtures_team_participantcoach.deleted_at IS NULL
            LEFT JOIN           sportsfixtures_team_participantuser ON
                                sportsfixtures_team_participantuser.match_id = sportsfixtures_match.id AND
                                sportsfixtures_team_participantuser.user_id IN {$placeholders} AND
                                sportsfixtures_team_participantuser.deleted_at IS NULL
            LEFT JOIN           sportsfixtures_team awayTeam ON
                                sportsfixtures_match.away_team_id = awayTeam.id AND
                                awayTeam.deleted_at IS NULL
            LEFT JOIN           sportsfixtures_team homeTeam ON
                                sportsfixtures_match.home_team_id = homeTeam.id AND
                                homeTeam.deleted_at IS NULL
            LEFT JOIN           sportsfixtures_team_coach ON
                                (sportsfixtures_team_coach.team_id = awayTeam.id OR sportsfixtures_team_coach.team_id = homeTeam.id)
            LEFT JOIN           sportsfixtures_coach ON
                                sportsfixtures_coach.id = sportsfixtures_team_coach.coach_id AND
                                sportsfixtures_coach.user_id IN {$placeholders} AND
                                sportsfixtures_coach.deleted_at IS NULL
            LEFT JOIN           sportsfixtures_team_user ON
                                (sportsfixtures_team_user.team_id = awayTeam.id OR sportsfixtures_team_user.team_id = homeTeam.id) AND
                                sportsfixtures_team_user.user_id IN {$placeholders}
            WHERE               (sportsfixtures_team_participantcoach.user_id IN {$placeholders} OR
                                sportsfixtures_team_participantuser.user_id IN {$placeholders} OR
                                sportsfixtures_coach.user_id IN {$placeholders} OR
                                sportsfixtures_team_user.user_id IN {$placeholders})
                                {$historical}
        ";

        $statement = $this->database->prepareQuery($query);

        if ($statement->execute(Builder::getArrayParameters($userIds, 'userId'))) {
            $result = $statement->fetchAll(\PDO::FETCH_COLUMN);
            return $this->sortMatches($this->getMatchMapper()->findByColumn('id', $result));
        }

        return new Collection();
    }


    /**
     * @param int  $teamId
     * @param bool $includeHistorical Whether to also return matches that have been played
     * @param int  $days
     * @return Collection|Match[]
     * @throws UnexpectedValueException
     */
    public function getTeamMatches(int $teamId, bool $includeHistorical, int $days)
    {
        /** @var Collection|Match[] $matches */
        $matches = new Collection();
        if (!$teamId) {
            return $matches;
        }

        // Get the team we are looking at

        try {
            $team = $this->getTeamMapper()->findById($teamId);

            // Load all home and away matches for the team

            $homeMatches = $this->getMatchMapper()->findByColumn('home_team_id', $team->getId());
            $awayMatches = $this->getMatchMapper()->findByColumn('away_team_id', $team->getId());

            /** @var Collection|Match[] $allMatches */
            $allMatches = $homeMatches->mergeUnique($awayMatches, 'id');

            // Filter matches
            foreach ($allMatches as $match) {
                $matchDay = clone $match->getStartAt();
                $matchDay->endOfDay();

                if ($includeHistorical || $matchDay->isFuture()) {
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
        } catch (MapperException $exception) {
            return $matches;
        }
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
