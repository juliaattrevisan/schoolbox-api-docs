<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Coach;

use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Role;
use Schoolbox\SportsFixtures\Team;
use Schoolbox\UserMapper;
use Schoolbox\User as UserModel;

/**
 * The mapper for a sports fixtures coach.
 *
 * @method Model createModel()
 * @package Schoolbox\SportsFixtures\Coach
 */
class Mapper extends BaseMapper implements BaseMapper\UsableAsTagSource
{
    /**
     * Setup mapping
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_coach');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
//        $this->joinsVia(
//            'sportsfixtures_team_coach',
//            Team\Mapper::class,
//            'id',
//            'sportsfixtures_team_id',
//            'sportsfixtures_coach_id',
//            null,
//            null,
//            null,
//            'teams'
//        );
    }

    /**
     * Hydrate user as well by default.
     *
     * @param \PDOStatement $statement
     * @return Collection
     * @throws BaseMapper\Exception
     */
    public function hydrateSelect(\PDOStatement $statement)
    {
        $collection = new Collection();
        foreach ($statement as $row) {
            $model = $this->hydrate($row);
            if (isset($row['u.id'])) {
                $user = $this->hydrateJoin($row, 'user', 'u');
                if ($user instanceof UserModel) {
                    $model->setUser($user);
                    $role = $this->hydrateJoin($row, 'user.role', 'ur');
                    if ($role instanceof Role) {
                        $user->setRole($role);
                    }
                }
            }
            $collection->add($model);
        }
        return $collection;
    }

    /**
     * Retrieves a list of items for a tag cloud.
     *
     * @param UserModel $user
     * @param string $keyword
     * @param int $offset
     * @param int $limit
     * @return Collection|Model[]
     */
    public function findForTag(UserModel $user, $keyword, $offset = 0, $limit = 0)
    {
        // Join side: tag w/o user is okay, so is user w/o tag - therefore FULL OUTER JOIN.
        // But that doesn't exist in MySQL! Emulate with LEFT JOIN U RIGHT JOIN
        // Search match: close enough to user AJAX search
        $selectPart = "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('user', 'u')},
                {$this->getJoinColumnString('user.role', 'ur')}
            FROM {$this->getTable()}
        ";
        $wherePart = "
            WHERE {$this->getTable()}.name LIKE :search
            OR    CONCAT(u.firstname, ' ', u.lastname) LIKE :search
        ";
        $joinPart = "
            JOIN {$this->getJoinSql('user', '', 'u')}
            LEFT JOIN {$this->getJoinSql('user.role', 'u', 'ur')}
        ";
        $query = "
            ($selectPart LEFT $joinPart $wherePart)
            UNION ALL
            ($selectPart RIGHT $joinPart $wherePart)
            ORDER BY name ASC, `u.lastname` ASC, `u.firstname` ASC
        ";
        if ($offset > 0) {
            $query .= ' LIMIT :limit OFFSET :offset';
        } elseif ($limit > 0) {
            $query .= ' LIMIT :limit';
        }
        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue('search', '%'.$this->getDatabase()->quoteForLike($keyword).'%', \PDO::PARAM_STR);
        if ($offset > 0) {
            $statement->bindValue('offset', intval($offset), \PDO::PARAM_INT);
            $statement->bindValue('limit', intval($limit), \PDO::PARAM_INT);
        } elseif ($limit > 0) {
            $statement->bindValue('limit', intval($limit), \PDO::PARAM_INT);
        }
        $statement->execute();
        return $this->hydrateSelect($statement);
    }

    /**
     * Retrieves coaches for the given team.
     *
     * @param Team\Model $team
     * @return Collection|Model[]
     */
    public function findForTeam(Team\Model $team)
    {
        $query = "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('user', 'u')},
                {$this->getJoinColumnString('user.role', 'ur')}
            FROM {$this->getTable()}
            INNER JOIN sportsfixtures_team_coach stc ON {$this->getTable()}.id = stc.sportsfixtures_coach_id
            LEFT JOIN {$this->getJoinSql('user', '', 'u')}
            LEFT JOIN {$this->getJoinSql('user.role', 'u', 'ur')}
            WHERE stc.sportsfixtures_team_id = :teamId
            ORDER BY name ASC
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue('teamId', $team->getId(), \PDO::PARAM_INT);
        $statement->execute();
        return $this->hydrateSelect($statement);
    }
}
