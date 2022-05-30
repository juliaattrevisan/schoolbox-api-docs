<?php

/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Matchup;

use OutOfBoundsException;
use PDO;
use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Fixtures\Matchup\Model as Matchup;
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
 *
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
        $this->uses('finish_at', self::TYPE_DATETIME);
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
     * @param Model $matchup
     * @return bool
     */
    public function saveItinerary(Matchup $matchup)
    {
        if ($matchup->getItinerary() === null) {
            // Don't attempt to save if join is not hydrated
            return true;
        }

        // ensure the itinerary items all point to the correct match (ie. this one)
        foreach ($matchup->getItinerary() as $item) {
            $item->setMatchup($matchup);
        }

        return $this->cascadeJoin('itinerary', ['match_id' => $matchup->getId()], $matchup->getItinerary());
    }

    /**
     * Delete all Itinerary Items associated with this Match
     * when it's being deleted
     *
     * @param Matchup $matchup
     */
    public function deleteItineraryItems(Matchup $matchup)
    {
        $allItineraryItems = ItineraryItemMapper::getInstance()->findByColumn('match_id', $matchup->getId());

        foreach ($allItineraryItems as $currentItineraryItem) {
            ItineraryItemMapper::getInstance()->delete($currentItineraryItem);
        }
    }

    /**
     * Delete all Results associated with this Match
     *
     * @param Model $matchup
     */
    public function deleteResults(Matchup $matchup)
    {
        $allResults = ResultMapper::getInstance()->findByColumn('match_id', $matchup->getId());

        foreach ($allResults as $currentResult) {
            ResultMapper::getInstance()->delete($currentResult);
        }
    }


    /**
     * Find all the Matchup instances where $user is involved via their current Team,
     * within the date range specified
     *
     * @param \Schoolbox\User           $user
     * @return GenericCollection
     * @throws BaseMapper\DefinitionException
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function findInvolvedMatchups(
        \Schoolbox\User $user
    ): GenericCollection {

        /* Make a pretty significant assumption here:
         *
         * Teams from Clipboard always import as the Home Team
         *
         * There used to be support for a person being in either the Home
         * team or the Away team, but then you had to know which one of those
         * caused this record to be included in the stack - because you need
         * to link to a team id on the front end via the calendar modal
         */
        $query = "
            SELECT
                {$this->getColumnString('sm')},
                {$this->getJoinColumnString('result', 'sr1')},
                {$this->getJoinColumnString('result', 'sr2')},
                {$this->getJoinColumnString('venue', 'sv')}
            FROM sportsfixtures_match sm
            LEFT JOIN {$this->getJoinSql('venue', 'sm', 'sv')}
            LEFT JOIN sportsfixtures_team st1 ON sm.home_team_id = st1.id
            LEFT JOIN sportsfixtures_team st2 ON sm.away_team_id = st2.id
            LEFT JOIN sportsfixtures_team_user stu1 ON st1.id = stu1.team_id
            LEFT JOIN sportsfixtures_team_user stu2 ON st2.id = stu2.team_id
            LEFT JOIN sportsfixtures_result sr1 ON st1.id = sr1.team_id AND sm.id = sr1.match_id
            LEFT JOIN sportsfixtures_result sr2 ON st2.id = sr2.team_id AND sm.id = sr2.match_id
            WHERE (stu1.user_id = :userId OR stu2.user_id = :userId)
            AND sm.deleted_at IS NULL
            AND st1.deleted_at IS NULL
            AND st2.deleted_at IS NULL
            ORDER BY sm.start_at
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([
            'userId' => $user->getId(),
        ]);

        /** @var GenericCollection|Matchup[] $matches */
        $matches = $this->createCollection();

        /** @var Collection|Venue[] $venues */
        $venues = new Collection();

        while ($row = $statement->fetch()) {
            $matchId = $row['sm.id'];

            /* Set up us the match in the collection
             * Only hydrate all this stuff if it never existed previously
             */
            if (!($matches->getOffset($matchId) instanceof Matchup)) {
                /* Hydrate venues, if extant
                 */
                /** @var Matchup $matchup */
                $matchup = $this->hydrate($row, 'sm');

                if (!$venues->getOffset($row['sm.venue_id']) instanceof Venue) {
                    $venues->setOffset($row['sm.venue_id'], $this->hydrateJoin($row, 'venue', 'sv'));
                }

                $matchup->setVenue($venues->getOffset($row['sm.venue_id']));

                /* Hydrate results
                 */
                /** @var \Schoolbox\Fixtures\Result\Model $homeTeamResult */
                $homeTeamResult = $this->hydrateJoin($row, 'result', 'sr1');

                /** @var \Schoolbox\Fixtures\Result\Model $awayTeamResult */
                $awayTeamResult = $this->hydrateJoin($row, 'result', 'sr2');

                $matchup->setHomeTeamResult($homeTeamResult);
                $matchup->setAwayTeamResult($awayTeamResult);

                $matches->setOffset($matchup->getId(), $matchup);
            }
        }

        return $matches;
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
        /** @var GenericCollection|Matchup[] $matches */
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
            if (!($matches->getOffset($matchId) instanceof Matchup)) {
                /** @var Matchup $matchup */
                $matchup = $this->hydrate($row);

                if (!$divisions->getOffset($row['division_id']) instanceof Division) {
                    $divisions->setOffset($row['division_id'], $this->hydrateJoin($row, 'division', 'd'));
                }
                $matchup->setDivision($divisions->getOffset($row['division_id']));

                if (!$seasons->getOffset($row['season_id']) instanceof Season) {
                    $seasons->setOffset($row['season_id'], $this->hydrateJoin($row, 'season', 's'));
                }
                $matchup->setSeason($seasons->getOffset($row['season_id']));

                if (!$venues->getOffset($row['venue_id']) instanceof Venue) {
                    $venues->setOffset($row['venue_id'], $this->hydrateJoin($row, 'venue', 'v'));
                }
                $matchup->setVenue($venues->getOffset($row['venue_id']));

                // Optional relationship
                if (!$teams->getOffset($row['home_team_id']) instanceof Team) {
                    $teams->setOffset($row['home_team_id'], $this->hydrateJoin($row, 'home_team', 'h'));
                }
                $matchup->setHomeTeam($teams->getOffset($row['home_team_id']));

                // Optional relationship
                if (!$teams->getOffset($row['away_team_id']) instanceof Team) {
                    $teams->setOffset($row['away_team_id'], $this->hydrateJoin($row, 'away_team', 'a'));
                }
                $matchup->setAwayTeam($teams->getOffset($row['away_team_id']));

                // Optional relationship
                if (!$schools->getOffset($row['home_school_id']) instanceof School) {
                    $schools->setOffset($row['home_school_id'], $this->hydrateJoin($row, 'home_school', 'hs'));
                }
                $matchup->setHomeSchool($schools->getOffset($row['home_school_id']));

                // Optional relationship
                if (!$schools->getOffset($row['away_school_id']) instanceof School) {
                    $schools->setOffset($row['away_school_id'], $this->hydrateJoin($row, 'away_school', 'as'));
                }
                $matchup->setAwaySchool($schools->getOffset($row['away_school_id']));

                $matches->setOffset($matchup->getId(), $matchup);
            }

            /* Append all Result instances as you go
             */
            if ($row['r.team_id']) {
                $resultsKey = $matchId . '-' . $row['r.team_id'];
                if (!$results[$resultsKey] instanceof Result) {
                    /** @var Result $result */
                    $result = $this->hydrateJoin($row, 'result', 'r');
                    $result->setStatus($this->hydrateJoin($row, 'result.status', 'rs'));
                    $results[$resultsKey] = $result;

                    // Figure out if this result if for the home or away team
                    if (
                        $matches[$matchId]->getHomeTeam() != null &&
                        $matches[$matchId]->getHomeTeam()->getId() == $result->getTeam()->getId()
                    ) {
                        $matches[$matchId]->setHomeTeamResult($result);
                    } else {
                        if (
                            $matches[$matchId]->getAwayTeam() != null &&
                            $matches[$matchId]->getAwayTeam()->getId() == $result->getTeam()->getId()
                        ) {
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
     * @return Matchup
     * @throws Exception
     * @throws OutOfBoundsException
     * @throws \PDOException
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
     * @param Matchup $object
     * @param string $verb
     * @param Matchup $previous
     * @return bool
     * @throws \Exception
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        $sessionUser = $this->getSessionUser();
        if (!$sessionUser) {
            return false;
        }

        $diff = $previous && ($verb != self::METHOD_DELETE) ? $this->diffForNotification($object, $previous) : null;

        // create a new event, and send it to the scheduler
        try {
            $this->getEventPropagator()->propagate($sessionUser, $verb, $object, $this, $diff);
        } catch (\ErrorException $e) {
            //Unable to connect to notification system
            return false;
        }

        return true;
    }

    /**
     * Diff Match objects for notification purpose.
     * @param Matchup $object
     * @param Matchup $previous
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
