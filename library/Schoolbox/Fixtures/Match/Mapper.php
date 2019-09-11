<?php
/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Match;


use OutOfBoundsException;
use PDO;
use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper As BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Fixtures\Match\Model as Match;
use Schoolbox\Fixtures\Division\Model as Division;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Season\Model as Season;
use Schoolbox\Fixtures\Season\Mapper as SeasonMapper;
use Schoolbox\Fixtures\Team\Model as Team;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Venue\Model as Venue;
use Schoolbox\Fixtures\Venue\Mapper as VenueMapper;
use Schoolbox\Fixtures\ItineraryItem\Model as ItineraryItem;
use Schoolbox\Fixtures\ItineraryItem\Mapper as ItineraryItemMapper;
use Schoolbox\Fixtures\Result\Model as Result;
use Schoolbox\Fixtures\Result\Mapper as ResultMapper;
use Schoolbox\Fixtures\School\Model as School;
use Schoolbox\Fixtures\School\Mapper as SchoolMapper;
use Schoolbox\Collection;

/**
 * Class Mapper
 * @method Model createModel()
 * @method Model findById($identifierValue)
 * @package Schoolbox\Fixtures\Match
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
        $this->joins('home_school_id', SchoolMapper::class, 'id', null, null, 'homeSchool', 'home_school');
        $this->joins('away_school_id', SchoolMapper::class, 'id', null, null, 'awaySchool', 'away_school');
        $this->uses('notes', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        /* Reverse join thingo
         */
        $this->hasJoin('result', [
            'mapper'    => ResultMapper::class,
            'this_key'  => 'id',
            'other_key' => 'match_id',
        ]);

        /* Reverse join thingo
         */
        $this->hasJoin('itinerary', [
            'mapper'    => ItineraryItemMapper::class,
            'this_key'  => 'id',
            'other_key' => 'match_id',
        ]);

        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'saveItinerary']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deleteItineraryItems']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deleteResults']);
    }

    /**
     * Perform an intersection of the itinerary given against what exists in the database:
     *  - any records that exist in memory are to be persisted to the DB;
     *  - any records that exist in DB but not memory are to be deleted.
     *
     * @param Model $match
     * @return bool
     */
    public function saveItinerary(Match $match)
    {
        if ($match->getItinerary() === null) {
            // Don't attempt to save if join is not hydrated
            return true;
        }

        // ensure the itinerary items all point to the correct match (ie. this one)
        foreach ($match->getItinerary() as $item) {
            $item->setMatch($match);
        }

        return $this->cascadeJoin('itinerary', ['match_id' => $match->getId()], $match->getItinerary());
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
				{$this->getJoinColumnString('season', 's')},
				{$this->getJoinColumnString('home_school', 'hs')},
				{$this->getJoinColumnString('away_school', 'as')},
				{$this->getJoinColumnString('venue', 'v')},
				{$this->getJoinColumnString('itinerary', 'i')},
				{$this->getJoinColumnString('result', 'r')},
				{$this->getJoinColumnString('result.status', 'rs')}
        ";
    }

    /**
     * @return string
     */
    protected function getFromString()
    {
        return "
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('home_team', null, 'h')}
            LEFT JOIN {$this->getJoinSql('away_team', null, 'a')}
            LEFT JOIN {$this->getJoinSql('division', null, 'd')}
            LEFT JOIN {$this->getJoinSql('season', null, 's', true)}
            LEFT JOIN {$this->getJoinSql('home_school', null, 'hs')}
            LEFT JOIN {$this->getJoinSql('away_school', null, 'as')}
            LEFT JOIN {$this->getJoinSql('venue', null, 'v')}
            LEFT JOIN {$this->getJoinSql('itinerary', null, 'i')}
            LEFT JOIN {$this->getJoinSql('result', null, 'r')}
            LEFT JOIN {$this->getJoinSql('result.status', 'r', 'rs')}
        ";
    }

    /**
     * @param PDOStatement $statement
     * @return GenericCollection|Model[]
     * @throws Exception
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var Collection|Match[] $matches */
        $matches = $this->createCollection();

        /** @var Collection|Division[] $divisions */
        $divisions = new Collection();

        /** @var Collection|Season[] $seasons */
        $seasons = new Collection();

        /** @var Collection|Venue[] $venues */
        $venues = new Collection();

        /** @var Collection|Team[] $teams */
        $teams = new Collection();

        /** @var Collection|School[] $schools */
        $schools = new Collection();

        /** @var Collection|Result[] $results */
        $results = new Collection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $matchId = $row['id'];

            /* Set up us the match in the collection
             * Only hydrate all this stuff if it never existed previously
             */
            if (!($matches->getOffset($matchId) instanceof Match)) {
                /** @var Match $match */
                $match = $this->hydrate($row);

                if (!$divisions->getOffset($row['division_id']) instanceof Division) {
                    $divisions->setOffset($row['division_id'], $this->hydrateJoin($row, 'division', 'd'));
                }
                $match->setDivision($divisions->getOffset($row['division_id']));

                if (!$seasons->getOffset($row['season_id']) instanceof Season) {
                    $seasons->setOffset($row['season_id'], $this->hydrateJoin($row, 'season', 's'));
                }
                $match->setSeason($seasons->getOffset($row['season_id']));

                if (!$venues->getOffset($row['venue_id']) instanceof Venue) {
                    $venues->setOffset($row['venue_id'], $this->hydrateJoin($row, 'venue', 'v'));
                }
                $match->setVenue($venues->getOffset($row['venue_id']));

                // Optional relationship
                if (!$teams->getOffset($row['home_team_id']) instanceof Team) {
                    $teams->setOffset($row['home_team_id'], $this->hydrateJoin($row, 'home_team', 'h'));
                }
                $match->setHomeTeam($teams->getOffset($row['home_team_id']));

                // Optional relationship
                if (!$teams->getOffset($row['away_team_id']) instanceof Team) {
                    $teams->setOffset($row['away_team_id'], $this->hydrateJoin($row, 'away_team', 'a'));
                }
                $match->setAwayTeam($teams->getOffset($row['away_team_id']));

                // Optional relationship
                if (!$schools->getOffset($row['home_school_id']) instanceof School) {
                    $schools->setOffset($row['home_school_id'], $this->hydrateJoin($row, 'home_school', 'hs'));
                }
                $match->setHomeSchool($schools->getOffset($row['home_school_id']));

                // Optional relationship
                if (!$schools->getOffset($row['away_school_id']) instanceof School) {
                    $schools->setOffset($row['away_school_id'], $this->hydrateJoin($row, 'away_school', 'as'));
                }
                $match->setAwaySchool($schools->getOffset($row['away_school_id']));

                $matches->setOffset($match->getId(), $match);
            }

            /* Append all Result instances as you go
             */
            if ($row['r.team_id']) {
                $resultsKey = $matchId.'-'.$row['r.team_id'];
                if (!$results[$resultsKey] instanceof Result) {
                    /** @var Result $result */
                    $result = $this->hydrateJoin($row, 'result', 'r');
                    $result->setStatus($this->hydrateJoin($row, 'result.status', 'rs'));
                    $results[$resultsKey] = $result;

                    // Figure out if this result if for the home or away team
                    if ($matches[$matchId]->getHomeTeam() != null &&
                        $matches[$matchId]->getHomeTeam()->getId() == $result->getTeam()->getId()) {
                        $matches[$matchId]->setHomeTeamResult($result);
                    } else {
                        if ($matches[$matchId]->getAwayTeam() != null &&
                            $matches[$matchId]->getAwayTeam()->getId() == $result->getTeam()->getId()) {
                            $matches[$matchId]->setAwayTeamResult($result);
                        } else {
                            // This result doesn't belong to either team.
                            // Who knows how it got here, but it is safe to ignore.
//                            throw new \LogicException('Cannot match this result with a match team');
                        }
                    }
                }
            }


            /* Append all ItineraryItems as you go
             */
            if ($row['i.id']) {
                if (!($matches->getOffset($matchId)->getItinerary()->getOffset($row['i.id']) instanceof ItineraryItem)) {
                    $itineraryItem = $this->hydrateJoin($row, 'itinerary', 'i');

                    $matches->getOffset($matchId)->getItinerary()->setOffset($itineraryItem->getId(), $itineraryItem);
                }
            }
        }

        /* Order itinerary items in asc order
         */
        foreach ($matches as $current) {
            $current->getItinerary()->sortBy('startAt', 'asc');
        }

        return $matches;
    }

    /**
     * Find the next match for a team
     * The team can be either the home or away team
     *
     * @param Team $team
     * @throws OutOfBoundsException
     * @return Match
     */
    public function findNextMatch(Team $team)
    {
        $now = \Alaress_Date_Format::now();
        $query = "
            SELECT {$this->getColumnString()}
            FROM {$this->getTable()}
            WHERE start_at > :now
            AND (home_team_id = :teamId OR away_team_id = :teamId)
            ORDER BY start_at ASC
            LIMIT 1
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([
            'teamId' => $team->getId(),
            'now'    => $now->getDatabase()
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return $this->hydrate($row);
        }

        throw new OutOfBoundsException("Given team has no future matches");
    }

    /**
     * Match notifications
     * @see  \Schoolbox\Mapper::raiseNotification()
     * @param Match  $object
     * @param string $verb
     * @param Match  $previous
     * @return bool
     * @throws \Exception
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        if (!isset($this->sessionData['user'])) {
            return false;
        }

        $diff = $previous && ($verb != self::METHOD_DELETE) ? $this->diffForNotification($object, $previous) : null;

        // create a new event, and send it to the scheduler
        try {
            $this->getEventPropagator()->propagate($this->sessionData['user'], $verb, $object, $this, $diff);
        }
        catch( \ErrorException $e) {
            //Unable to connect to notification system
            return false;
        }

        return true;
    }

    /**
     * Diff Match objects for notification purpose.
     * @param Match $object
     * @param Match $previous
     * @return array
     * @throws Exception
     */
    private function diffForNotification($object, $previous)
    {
        // Get diff of match
        $diff = $this->diff($object, $previous);

        // Append itinerary changes to the diff
        $diffMethod = function (ItineraryItem $a, ItineraryItem $b) {
            return $a->getStartAt()->getUnixTimeStamp() === $b->getStartAt()->getUnixTimeStamp()
                && $a->getDescription() === $b->getDescription();
        };

        // Diff in the both directions to catch deleted itineraries
        $itineraryModifiedCurrent = $object->getItinerary()->diff($previous->getItinerary(), $diffMethod);
        $itineraryModifiedPrevious = $previous->getItinerary()->diff($object->getItinerary(), $diffMethod);

        // If any itinerary changes, add earliest time of current and previous itineraries into $diff
        // The time will be used to determine whether to trigger itinerary change notification
        if ($itineraryModifiedCurrent->count() > 0 || $itineraryModifiedPrevious->count() > 0) {
            $itineraryStartAtCurrent = 0;
            foreach ($itineraryModifiedCurrent as $itinerary) {
                if ($itineraryStartAtCurrent === 0 || $itinerary->getStartAt()->getUnixTimeStamp() < $itineraryStartAtCurrent) {
                    $itineraryStartAtCurrent = $itinerary->getStartAt()->getUnixTimeStamp();
                }
            }
            $itineraryStartAtPrevious = 0;
            foreach ($itineraryModifiedPrevious as $itinerary) {
                if ($itineraryStartAtPrevious === 0 || $itinerary->getStartAt()->getUnixTimeStamp() < $itineraryStartAtPrevious) {
                    $itineraryStartAtPrevious = $itinerary->getStartAt()->getUnixTimeStamp();
                }
            }
            $diff['itineraryStartAt'] = [$itineraryStartAtCurrent, $itineraryStartAtPrevious];
        }
        return $diff;
    }
}
