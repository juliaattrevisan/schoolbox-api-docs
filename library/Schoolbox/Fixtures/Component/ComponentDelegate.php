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

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\Fixtures\Matchup\Model as Matchup;
use Schoolbox\Fixtures\Matchup\Mapper as MatchupMapper;
use Schoolbox\Fixtures\Result\Model as Result;
use Schoolbox\Fixtures\Result\Mapper as ResultMapper;
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
     * @var MatchupMapper
     */
    protected $matchupMapper;

    /**
     * @var ResultMapper
     */
    protected $resultMapper;

    /**
     * @var IConnector
     */
    protected $database;

    /**
     * Delegate constructor.
     *
     * @param IConnector $database
     */
    public function __construct(IConnector $database)
    {
        $this->database = $database;

        $this
            ->setTeamMapper(TeamMapper::getInstance())
            ->setTeamPlayerMapper(TeamPlayerMapper::getInstance())
            ->setTeamParticipantMapper(TeamParticipantMapper::getInstance())
            ->setMatchupMapper(MatchupMapper::getInstance())
            ->setResultMapper(ResultMapper::getInstance());
    }


    /**
     * Gets a collection of matches with only relevant teams and players attached, sorted by 'startAt'
     *
     * @param array $userIds
     * @param bool  $includeHistorical Whether to return matches in the past
     * @param int   $days
     * @return Collection|Matchup[]
     * @throws Exception
     */
    public function getMatchesForUsers(array $userIds, bool $includeHistorical, int $days)
    {
        /* Decide the upper threshold determined by $days, if greater than zero
         */
        $date = null;
        $thresholdDate = null;
        $threshold = '';

        if ($days) {
            $thresholdDate = \Alaress_Date_Format::now()->addDays($days)->endOfDay()->getDatabase();

            $threshold = ' AND (sportsfixtures_match.start_at) < :thresholdDate';
        }

        $historical = '';

        if (!$includeHistorical) {
            $historical = 'AND DATE(sportsfixtures_match.start_at) >= DATE(NOW())';
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
                                {$threshold}
        ";

        $statement = $this->database->prepareQuery($query);

        $params = Builder::getArrayParameters($userIds, 'userId');

        if (null !== $thresholdDate) {
            $params = array_merge($params, ['thresholdDate' => $thresholdDate]);
        }

        if ($statement->execute($params)) {
            $result = $statement->fetchAll(\PDO::FETCH_COLUMN);

            return $this->sortMatches($this->getMatchupMapper()->findByColumn('id', $result));
        }

        return new Collection();
    }


    /**
     * @param int  $teamId
     * @param bool $includeHistorical Whether to also return matches that have been played
     * @param int  $days
     * @return Collection|Matchup[]
     * @throws UnexpectedValueException
     */
    public function getTeamMatches(int $teamId, bool $includeHistorical, int $days)
    {
        /* Decide the upper threshold determined by $days, if greater than zero
         */
        $threshold = null;

        if ($days) {
            $threshold = \Alaress_Date_Format::now()->addDays($days)->endOfDay();
        }

        /* Remember the relevant matches and whether they were in the past
         */
        /** @var Collection|Matchup[] $matches */
        $matches = new Collection();

        if (!$teamId) {
            return $matches;
        }

        $playedMatchIds = [];

        try {
            /* Load all home and away matches for the team we've configured in the component
             */
            $team = $this->getTeamMapper()->findById($teamId);

            $homeMatches = $this->getMatchupMapper()->findByColumn('home_team_id', $team->getId());
            $awayMatches = $this->getMatchupMapper()->findByColumn('away_team_id', $team->getId());

            /** @var Collection|Matchup[] $allMatches */
            $allMatches = $homeMatches->mergeUnique($awayMatches, 'id');

            // Filter matches
            foreach ($allMatches as $matchup) {
                $matchDay = clone $matchup->getStartAt();
                $matchDay->endOfDay();

                if ($includeHistorical && $matchDay->isPast()) {
                    /* Include matches previously played
                     */
                    $matches[$matchup->getId()] = $matchup;
                    $playedMatchIds[] = $matchup->getId();
                } elseif ($matchDay->isFuture()) {
                    /* If no threshold was specified, add it to the list
                     * This shows all future matches
                     *
                     * Otherwise,
                     * Check that the future match day falls within the threshold defined earlier
                     * If it does, add it to the list
                     */
                    if (null === $threshold || $matchDay->lte($threshold)) {
                        $matches[$matchup->getId()] = $matchup;
                    }
                }
            }

            // Are we getting matches that have been played ?
            if ($includeHistorical) {

                /** @var Collection|Result[] $results */
                $results = $this->getResultMapper()->findByColumnList(['match_id' => $playedMatchIds]);

                foreach ($results as $result) {
                    $matches[$result->getMatchup()->getId()]->getResults()[$result->getTeam()->getId()] = $result;
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
     * @param Collection|Matchup[] $matches
     * @return Collection|Matchup[]
     */
    public function sortMatches(Collection $matches)
    {
        // Sort matches by date order
        $matches->sortByCallback(function ($a, $b) {
            /** @var Matchup $a */
            /** @var Matchup $b */
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
     * @return MatchupMapper
     */
    public function getMatchupMapper()
    {
        return $this->matchupMapper;
    }

    /**
     * @param MatchupMapper $matchupMapper
     * @return static
     */
    public function setMatchupMapper($matchupMapper)
    {
        $this->matchupMapper = $matchupMapper;
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
