<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Team\Position;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper As BaseMapper;
use Schoolbox\Role as Role;
use Schoolbox\User as User;
use Schoolbox\UserMapper;
use Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;
use Schoolbox\SportsFixtures\Team\Model as Team;
use Schoolbox\SportsFixtures\Division\Model as Division;
use Schoolbox\SportsFixtures\Position\Mapper as PositionMapper;
use Schoolbox\SportsFixtures\Position\Model as Position;

/**
 * Mapper for the relation between teams and users.
 *
 * @method Model createModel()
 * @method Model findById($id)
 * @package Schoolbox\SportsFixtures\Team\User
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_team_position');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('team_id', TeamMapper::class, 'id', null, null, 'team', 'team');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->joins('position_id', PositionMapper::class, 'id', null, null, 'position', 'position');
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
                {$this->getJoinColumnString('position', 'p')}
            FROM {$this->database->quoteIdentifier($this->table)}
            INNER JOIN {$this->getJoinSql('user', '', 'u')}
            INNER JOIN {$this->getJoinSql('user.role', 'u', 'ur')}
            INNER JOIN {$this->getJoinSql('team', '', 't')}
            INNER JOIN {$this->getJoinSql('position', '', 'p')}
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
                ->setId($row['id']);

            // set user, team and position only if retrieved properly

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
                $model->setTeam($team);
            }

            $position = $this->hydrateJoin($row, 'position', 'p');

            if ($position instanceof Position) {
                $model->setPosition($position);
            }

            $collection->add($model);
        }
        return $collection;
    }

    /**
     * Finds all team memberships for a user in a division.
     *
     * @param User         $user
     * @param Division     $division
     * @return Collection|Model[]
     */
    public function findForUserInDivision(User $user, Division $division)
    {
        $query = $this->getSelectString().' WHERE td.id = :divisionId AND u.id = :userId';
        $stmt  = $this->getDatabase()->prepare($query);
        $stmt->execute(['userId' => $user->getId(), 'divisionId' => $division->getId(),]);
        return $this->hydrateList($stmt);
    }
}
