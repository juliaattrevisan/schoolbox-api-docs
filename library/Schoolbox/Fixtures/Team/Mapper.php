<?php
/**
 * Team Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Team;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper As BaseMapper;
use Schoolbox\Fixtures\Coach;
use Schoolbox\Fixtures\Division;
use Schoolbox\Fixtures\Season;

/**
 * Class Mapper
 * @method Model createModel($idValue = null)
 * @method Model findById($id)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection|Model[] findByColumn($columnName, $columnValue, $options = [])
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
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('division_id', Division\Mapper::class, 'id', null, null, 'division', 'division');
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
                {$this->getJoinColumnString('sportsfixtures_team_coach', 'coaches')},
                {$this->getJoinColumnString('division', 'd')},
                {$this->getJoinColumnString('division.season', 's')}
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
            JOIN {$this->getJoinSql('division', null, 'd', true)}
            JOIN {$this->getJoinSql('division.season', 'd', 's', true)}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var Model[] $cache */
        $cache = [];
        /** @var Coach\Mapper $coachMapper */
        $coachMapper = $this->getJoinMapper('sportsfixtures_team_coach');

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($r = $statement->fetch(\PDO::FETCH_ASSOC)) {

            $teamId = $r['id'];

            if (empty($cache[$teamId])) {
                $cache[$teamId] = $this->hydrate($r);
                $cache[$teamId]->setCoaches($coachMapper->createCollection());
            }

            if ($r['coaches.id']) {
                $cache[$teamId]->getCoaches()->add($this->hydrateJoin($r, 'sportsfixtures_team_coach', 'coaches'));
            }

            /** @var Division\Model $division */
            $division = $this->hydrateJoin($r, 'division', 'd');
            $cache[$teamId]->setDivision($division);

            /** @var Season\Model $season */
            $season = $this->hydrateJoin($r, 'division.season', 's');
            $cache[$teamId]->getDivision()->setSeason($season);
        }

        return $this->createCollection($cache);
    }


    /**
     * Find all the teams whose names are like $keyword
     *
     * @param int $divisionId
     * @param string $keyword
     * @return Collection|Model[]
     */
    public function findByName($divisionId, $keyword)
    {
        $pb = $this->getPredicateBuilder();
        $pb->equals('division_id', $divisionId);
        $pb->like('name', "%{$this->getDatabase()->escapeForLike($keyword)}%");
        return $this->findByPredicates($pb);
    }

    /**
     * Delete all Players associated with this Team
     * when it's being deleted
     *
     * @param Model $team
     */
    public function deletePlayers(Model $team)
    {
        $teamPlayerMapper = Player\Mapper::getInstance();
        $teamPlayerMapper->delete($teamPlayerMapper->findByColumn('team_id', $team->getId()));

        /* TODO: Delete all Fixtures in this Team when this Team is deleted
         */
    }
}
