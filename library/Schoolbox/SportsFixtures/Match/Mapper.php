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


use Schoolbox\Mapper As BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\SportsFixtures\Match\Model as Match;
use Schoolbox\SportsFixtures\Division\Model as Division;
use Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;
use Schoolbox\SportsFixtures\Team\Model as Team;
use Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;
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
        $this->uses('name', self::TYPE_STRING);
        $this->uses('start_at', self::TYPE_DATETIME);
        $this->joins('home_team_id', TeamMapper::class, 'id', null, null, 'homeTeam', 'home_team');
        $this->joins('away_team_id', TeamMapper::class, 'id', null, null, 'awayTeam', 'away_team');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * Find a Match by its id
     *
     * Also include both Teams
     *
     * @param $id
     * @return Match
     * @throws Exception
     */
    public function findById($id)
    {
        $query = "
            SELECT
                {$this->getColumnString('m')},
                {$this->getJoinColumnString('home_team', 'h')},
                {$this->getJoinColumnString('away_team', 'a')},
				{$this->getJoinColumnString('division', 'd')}
            FROM {$this->getTable()} `m`
            LEFT JOIN {$this->getJoinSql('home_team', 'm', 'h')}
            LEFT JOIN {$this->getJoinSql('away_team', 'm', 'a')}
            LEFT JOIN {$this->getJoinSql('division', 'm', 'd')}
            WHERE m.id = ?
            AND m.deleted_at IS NULL
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(array($id));

        if ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {

            /** @var Match $match */
            $match = $this->hydrate($row, 'm');

            /** @var Division $division */
            $division = $this->hydrateJoin($row, 'division', 'd');

            /** @var Team $homeTeam */
            /** @var Team $awayTeam */
            $homeTeam = $this->hydrateJoin($row, 'home_team', 'h');
            $awayTeam = $this->hydrateJoin($row, 'away_team', 'a');

            $match->setDivision($division);
            $match->setHomeTeam($homeTeam);
            $match->setAwayTeam($awayTeam);

            return $match;
        }

        throw new Exception('Instance of object not found');
    }


    /**
     * Find all Match by their division id
     *
     * Also include both Teams
     *
     * @param int $divisionId
     * @return \Schoolbox\Collection
     * @throws Exception
     */
    public function findAllByDivision($divisionId)
    {
        $query = "
            SELECT
                {$this->getColumnString('m')},
                {$this->getJoinColumnString('home_team', 'h')},
                {$this->getJoinColumnString('away_team', 'a')}
            FROM {$this->getTable()} `m`
            LEFT JOIN {$this->getJoinSql('home_team', 'm', 'h')}
            LEFT JOIN {$this->getJoinSql('away_team', 'm', 'a')}
            WHERE m.division_id = ?
            AND m.deleted_at IS NULL
            ORDER BY m.start_at ASC
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([$divisionId]);

        $collection = new Collection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {

            /** @var Match $match */
            $match = $this->hydrate($row, 'm');

            /** @var Team $homeTeam */
            /** @var Team $awayTeam */
            $homeTeam = $this->hydrateJoin($row, 'home_team', 'h');
            $awayTeam = $this->hydrateJoin($row, 'away_team', 'a');

            $match->setHomeTeam($homeTeam);
            $match->setAwayTeam($awayTeam);

            $collection->add($match);
        }

        return $collection;
    }
}