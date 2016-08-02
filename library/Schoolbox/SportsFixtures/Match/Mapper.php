<?php
/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Match;


use PDOStatement;
use \UnexpectedValueException;
use Schoolbox\Mapper As BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\SportsFixtures\Match\Model as Match;
use Schoolbox\SportsFixtures\Division\Model as Division;
use Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;
use Schoolbox\SportsFixtures\Season\Model as Season;
use Schoolbox\SportsFixtures\Season\Mapper as SeasonMapper;
use Schoolbox\SportsFixtures\Team\Model as Team;
use Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;
use Schoolbox\SportsFixtures\Venue\Model as Venue;
use Schoolbox\SportsFixtures\Venue\Mapper as VenueMapper;
use Schoolbox\SportsFixtures\ItineraryItem\Model as ItineraryItem;
use Schoolbox\SportsFixtures\ItineraryItem\Mapper as ItineraryItemMapper;
use Schoolbox\SportsFixtures\Result\Model as Result;
use Schoolbox\SportsFixtures\Result\Mapper as ResultMapper;
use Schoolbox\Collection;

/**
 * Class Mapper
 * @method Model createModel()
 * @package Schoolbox\SportsFixtures\Match
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_match');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('division_id', DivisionMapper::class, 'id', null, null, 'division', 'division');
        $this->joins('season_id', SeasonMapper::class, 'id', null, null, 'season', 'season');
        $this->joins('venue_id', VenueMapper::class, 'id', null, null, 'venue', 'venue');
        $this->uses('name', self::TYPE_STRING);
        $this->uses('start_at', self::TYPE_DATETIME);
        $this->joins('home_team_id', TeamMapper::class, 'id', null, null, 'homeTeam', 'home_team');
        $this->joins('away_team_id', TeamMapper::class, 'id', null, null, 'awayTeam', 'away_team');
        $this->uses('home_school_id', self::TYPE_INTEGER);
        $this->uses('away_school_id', self::TYPE_INTEGER);
        $this->uses('notes', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        /* Reverse join thingo
         */
        $this->hasJoin('result', array(
            'mapper'    => ResultMapper::class,
            'this_key'  => 'id',
            'other_key' => 'match_id',
        ));

        /* Reverse join thingo
         */
        $this->hasJoin('itinerary', array(
            'mapper'    => ItineraryItemMapper::class,
            'this_key'  => 'id',
            'other_key' => 'match_id',
        ));

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'deleteItineraryItems'));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'deleteResults'));
    }

    /**
     * Delete all Itinerary Items associated with this Match
     * when it's being deleted
     *
     * @param Match $match
     */
    public function deleteItineraryItems(Match $match)
    {
        $allItineraryItems = ItineraryItemMapper::getInstance()->findByColumn('match_id', $match->getId());

        foreach ($allItineraryItems as $currentItineraryItem) {
            ItineraryItemMapper::getInstance()->delete($currentItineraryItem);
        }
    }

    /**
     * Delete all Results associated with this Match
     *
     * @param Model $match
     */
    public function deleteResults(Match $match)
    {
        $allResults = ResultMapper::getInstance()->findByColumn('match_id', $match->getId());

        foreach ($allResults as $currentResult) {
            ResultMapper::getInstance()->delete($currentResult);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('home_team', 'h')},
                {$this->getJoinColumnString('away_team', 'a')},
				{$this->getJoinColumnString('division', 'd')},
				{$this->getJoinColumnString('venue', 'v')},
				{$this->getJoinColumnString('itinerary', 'i')},
				{$this->getJoinColumnString('result', 'r')},
				{$this->getJoinColumnString('result.status', 'rs')}
        ";
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getFromString()
    {
        return "
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('home_team', null, 'h')}
            LEFT JOIN {$this->getJoinSql('away_team', null, 'a')}
            LEFT JOIN {$this->getJoinSql('division', null, 'd')}
            LEFT JOIN {$this->getJoinSql('venue', null, 'v')}
            LEFT JOIN {$this->getJoinSql('itinerary', null, 'i')}
            LEFT JOIN {$this->getJoinSql('result', null, 'r')}
            LEFT JOIN {$this->getJoinSql('result.status', 'r', 'rs')}
        ";
    }

    /**
     * {@inheritdoc}
     *
     * @param PDOStatement $statement
     * @return Collection
     * @throws Exception
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = new Collection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $matchId = $row['id'];

            /* Set up us the match in the collection
             * Only hydrate all this stuff if it never existed previously
             */
            if (!($collection->getOffset($matchId) instanceof Match)) {
                /** @var Match $match */
                $match = $this->hydrate($row);

                /** @var Division $division */
                $division = $this->hydrateJoin($row, 'division', 'd');

                /** @var Venue $venue */
                $venue = $this->hydrateJoin($row, 'venue', 'v');

                /** @var Team $homeTeam */
                /** @var Team $awayTeam */
                $homeTeam = $this->hydrateJoin($row, 'home_team', 'h');
                $awayTeam = $this->hydrateJoin($row, 'away_team', 'a');

                $match->setVenue($venue);
                $match->setDivision($division);
                $match->setHomeTeam($homeTeam);
                $match->setAwayTeam($awayTeam);

                $collection->setOffset($match->getId(), $match);
            }

            /* Append all Result instances as you go
             */
            if ($row['r.team_id']) {
                if (!($collection->getOffset($matchId)->getResult()->getOffset($row['r.team_id']) instanceof Result)) {
                    /** @var Result $result */
                    $result = $this->hydrateJoin($row, 'result', 'r');
                    $result->setStatus($this->hydrateJoin($row, 'result.status', 'rs'));

                    $collection->getOffset($matchId)->getResult()->setOffset($result->getTeam()->getId(), $result);
                }
            }

            /* Append all ItineraryItems as you go
             */
            if ($row['i.id']) {
                if (!($collection->getOffset($matchId)->getItinerary()->getOffset($row['i.id']) instanceof ItineraryItem)) {
                    $itineraryItem = $this->hydrateJoin($row, 'itinerary', 'i');

                    $collection->getOffset($matchId)->getItinerary()->setOffset($itineraryItem->getId(), $itineraryItem);
                }
            }
        }

        /* Order itinerary items in asc order
         */
        foreach ($collection as $current) {
            $current->getItinerary()->sortBy('startAt', 'asc');
        }

        return $collection;
    }

    /**
     * Find the next match for a team
     * The team can be either the home or away team
     *
     * @param Team $team
     * @return mixed
     */
    public function findNextMatch(Team $team)
    {
        $query = "
            SELECT {$this->getColumnString()}
            FROM {$this->getTable()}
            WHERE start_at > NOW()
            AND (home_team_id = :homeTeamId OR away_team_id = :awayTeamId)
            ORDER BY start_at ASC
            LIMIT 1
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([
            'homeTeamId' => $team->getId(),
            'awayTeamId' => $team->getId()
        ]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            return $this->hydrate($row);
        }

        throw new \OutOfBoundsException();
    }
}