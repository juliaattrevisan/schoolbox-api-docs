<?php
/**
 * Team Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Fixtures\Team;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper As BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Fixtures\Coach;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Team\Model as Team;

/**
 * Class Mapper
 * @method Team createModel()
 * @method Team findById($id)
 * @method Team hydrate(array $row, $alias = '', $callback = null)
 * @package Schoolbox\Fixtures\Division
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_team');
        $this->hasModel(Team::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('division_id', DivisionMapper::class, 'id', null, null, 'division', 'division');
        $this->uses('name', self::TYPE_STRING);
        $this->uses('url', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->joinsVia(
            'sportsfixtures_team_coach',
            Coach\Mapper::class,
            'id',
            'coach_id',
            'team_id',
            null,
            null,
            self::COLUMN_JOIN_MULTI,
            'coaches'
        );

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'deletePlayers'));
    }


    /**
     * Return the first part of the string required to select from this mapper
     *
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT 
                {$this->getColumnString()},
                {$this->getJoinColumnString('sportsfixtures_team_coach', 'coaches')}
        ";
    }


    /**
     * It seems we're going down this road...
     * To prevent cataclysmic backward-incompatibility, in the core, the FROM is absorbed into the SELECT... for now...
     *
     * @return string
     */
    protected function getFromString()
    {
        return "
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('sportsfixtures_team_coach', null, 'coaches')}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var Team[] $cache */
        $cache = [];

        while ($r = $statement->fetch(\PDO::FETCH_ASSOC)) {

            $teamId = $r['id'];

            if (empty($cache[$teamId])) {
                $cache[$teamId] = $this->hydrate($r);
            }

            if ($r['coaches.id']) {
                $cache[$teamId]->getCoaches()->add($this->hydrateJoin($r, 'sportsfixtures_team_coach', 'coaches'));
            }

        }

        return $this->createCollection($cache);
    }


    /**
     * Find all the teams whose names are like $keyword
     *
     * @param int $divisionId
     * @param string $keyword
     * @return Collection|Team[]
     * @throws Exception
     */
    public function findByName($divisionId, $keyword)
    {
        return $this->findByColumnList([
            'division_id' => $divisionId,
            '~name'       => "%{$this->database->quoteForLike($keyword)}%"
        ]);
    }

    /**
     * Delete all Players associated with this Team
     * when it's being deleted
     *
     * @param Team $team
     */
    public function deletePlayers(Team $team)
    {
        $teamPlayerMapper = Player\Mapper::getInstance();
        $teamPlayerMapper->delete($teamPlayerMapper->findByColumn('team_id', $team->getId()));

        /* TODO: Delete all Fixtures in this Team when this Team is deleted
         */
    }
}
