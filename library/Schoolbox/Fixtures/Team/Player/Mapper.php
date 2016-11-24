<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Fixtures\Team\Player;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper As BaseMapper;
use Schoolbox\Role as Role;
use Schoolbox\User as User;
use Schoolbox\UserMapper;
use Schoolbox\Fixtures\Team\Model as Team;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Division\Model as Division;

/**
 * Mapper for the relation between teams and users.
 *
 * @method Model createModel()
 * @method Model findById($id)
 * @method Collection|Model[] findByColumn($columnName, $columnValue, $options = array())
 * @package Schoolbox\Fixtures\Team\User
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_team_user');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('team_id', TeamMapper::class, 'id', null, null, 'team', 'team');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('sequence', self::TYPE_INTEGER);
    }

    /**
     * This mapper's find* methods always hydrate the user and team.
     *
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('user', 'u')},
                {$this->getJoinColumnString('user.role', 'ur')},
                {$this->getJoinColumnString('team', 't')},
                {$this->getJoinColumnString('team.division', 'td')}
            FROM {$this->database->quoteIdentifier($this->table)}
            INNER JOIN {$this->getJoinSql('user', '', 'u')}
            INNER JOIN {$this->getJoinSql('user.role', 'u', 'ur')}
            INNER JOIN {$this->getJoinSql('team', '', 't')}
            INNER JOIN {$this->getJoinSql('team.division', 't', 'td')}
        ";
    }

    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param  PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = new Collection();
        foreach ($statement as $row) {
            $model = $this->createModel()
                ->setId($row['id'])
                ->setSequence($row['sequence']);

            // set user and team only if retrieved properly
            $user = $this->hydrateJoin($row, 'user', 'u');
            if ($user instanceof User) {
                $role = $this->hydrateJoin($row, 'user.role', 'ur');
                if ($role instanceof Role) {
                    $user->setRole($role);
                }
                $model->setUser($user);
            }
            $team = $this->hydrateJoin($row, 'team', 't');
            if ($team instanceof Team) {
                $division = $this->hydrateJoin($row, 'team.division', 'td');
                if ($division instanceof Division) {
                    $team->setDivision($division);
                }
                $model->setTeam($team);
            }
            $collection->add($model);
        }
        return $collection;
    }

    /**
     * Finds all team memberships for a user in a division.
     *
     * @param User $user
     * @param Division $division
     * @return Collection|Model[]
     */
    public function findForUserInDivision(User $user, Division $division)
    {
        $query = $this->getSelectString() . ' WHERE td.id = :divisionId AND u.id = :userId';
        $stmt  = $this->getDatabase()->prepare($query);
        $stmt->execute(['userId' => $user->getId(), 'divisionId' => $division->getId(),]);
        return $this->hydrateList($stmt);
    }
}
