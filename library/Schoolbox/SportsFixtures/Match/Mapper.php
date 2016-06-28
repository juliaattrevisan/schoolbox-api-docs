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
use Schoolbox\Mapper As BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\SportsFixtures\Match\Model as Match;
use Schoolbox\SportsFixtures\Division\Model as Division;
use Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;
use Schoolbox\SportsFixtures\Team\Model as Team;
use Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;
use Schoolbox\SportsFixtures\Venue\Model as Venue;
use Schoolbox\SportsFixtures\Venue\Mapper as VenueMapper;
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
        $this->joins('venue_id', VenueMapper::class, 'id', null, null, 'venue', 'venue');
        $this->uses('name', self::TYPE_STRING);
        $this->uses('start_at', self::TYPE_DATETIME);
        $this->joins('home_team_id', TeamMapper::class, 'id', null, null, 'homeTeam', 'home_team');
        $this->joins('away_team_id', TeamMapper::class, 'id', null, null, 'awayTeam', 'away_team');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
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
				{$this->getJoinColumnString('venue', 'v')}
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

            $collection->add($match);
        }

        return $collection;
    }
}