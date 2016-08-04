<?php
/**
 * Divisions (eg. Soccer First, Seconds, Thirds) Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Division;

use \PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper As BaseMapper;
use Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
use Schoolbox\SportsFixtures\Sport\Model as Sport;
use Schoolbox\SportsFixtures\Season\Mapper as SeasonMapper;
use Schoolbox\SportsFixtures\Season\Model as Season;
use Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;
use Schoolbox\SportsFixtures\Division\Model as Division;

/**
 * Class Mapper
 * @method Division createModel()
 * @method Division findById($id)
 * @method Collection|Division[] findByColumn($columnName, $columnValue, $options = array())
 * @package Schoolbox\SportsFixtures\Division
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_division');
        $this->hasModel(Division::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('sport_id', SportMapper::class, 'id', null, null, 'sport', 'sport');
        $this->joins('season_id', SeasonMapper::class, 'id', null, null, 'season', 'season');
        $this->uses('name', self::TYPE_STRING);
        $this->uses('url', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'deleteTeams'));
    }

    /**
     * Delete all Teams associated with this Division
     * when it's being deleted
     *
     * @param Division $division
     */
    public function deleteTeams(Division $division)
    {
        $allTeams = TeamMapper::getInstance()->findByColumn('division_id', $division->getId());

        foreach ($allTeams as $currentTeam) {
            TeamMapper::getInstance()->delete($currentTeam);
        }

        $divisionUserMapper = User\Mapper::getInstance();
        $divisionUserMapper->delete($divisionUserMapper->findByColumn('division_id', $division->getId()));
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
                {$this->getJoinColumnString('sport', 'sp')},
                {$this->getJoinColumnString('season', 'se')}
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
            LEFT JOIN {$this->getJoinSql('sport', null, 'sp')}
            LEFT JOIN {$this->getJoinSql('season', null, 'se')}
        ";
    }

    /**
     * Hydrates the division, along with its sport and season
     *
     * @param PDOStatement $statement
     * @return Collection
     * @throws MapperException
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $divisions = new Collection();
        $sports    = new Collection();
        $seasons   = new Collection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC))
        {
            // Hydrate Division
            $divisions->setOffset($row[$this->getIdentifier()['column']], $this->hydrate($row));

            // Hydrate + Attach Sport
            if (!$sports->getOffset($row['sp.id']) instanceof Sport) {
                $sports->setOffset($row['sp.id'], $this->hydrateJoin($row, 'sport', 'sp'));
            }
            $divisions->getOffset($row[$this->getIdentifier()['column']])->setSport($sports->getOffset($row['sp.id']));

            // Hydrate + Attach Season
            if (!$seasons->getOffset($row['se.id']) instanceof Season) {
                $seasons->setOffset($row['se.id'], $this->hydrateJoin($row, 'season', 'se'));
            }
            $divisions->getOffset($row[$this->getIdentifier()['column']])->setSeason($seasons->getOffset($row['se.id']));
        }

        return $divisions;
    }
}