<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Division\User;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper As BaseMapper;
use Schoolbox\Role as RoleModel;
use Schoolbox\User as UserModel;
use Schoolbox\UserMapper;
use Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;
use Schoolbox\SportsFixtures\Division\Model as DivisionModel;
use Schoolbox\SportsFixtures\Team\User\Mapper as TeamUserMapper;
use Schoolbox\SportsFixtures\Team\User\Model as TeamUserModel;

/**
 * Mapper for the relation between divisions and users.
 *
 * @method Model createModel()
 * @method Model findById($id)
 * @package Schoolbox\SportsFixtures\Division\User
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_division_user');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('division_id', DivisionMapper::class, 'id', null, null, 'division', 'division');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->registerCallback(self::CALLBACK_POSTDELETE, [$this, 'deleteTeamMembership']);
    }

    /**
     * This mapper's find* methods always hydrate the user and division.
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
                {$this->getJoinColumnString('division', 'd')}
            FROM {$this->database->quoteIdentifier($this->table)}
            INNER JOIN {$this->getJoinSql('user', '', 'u')}
            INNER JOIN {$this->getJoinSql('user.role', 'u', 'ur')}
            INNER JOIN {$this->getJoinSql('division', '', 'd')}
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

            // set user and division only if retrieved properly
            $user = $this->hydrateJoin($row, 'user', 'u');
            if ($user instanceof UserModel) {
                $role = $this->hydrateJoin($row, 'user.role', 'ur');
                if ($role instanceof RoleModel) {
                    $user->setRole($role);
                }
                $model->setUser($user);
            }
            $division = $this->hydrateJoin($row, 'division', 'd');
            if ($division instanceof DivisionModel) {
                $model->setDivision($division);
            }
            $collection->add($model);
        }
        return $collection;
    }

    /**
     * Post-delete hook - remove this player from any teams in this division.
     *
     * @param Model $model
     */
    public function deleteTeamMembership(Model $model)
    {
        TeamUserMapper::getInstance()->delete(
            TeamUserMapper::getInstance()->findForUserInDivision(
                $model->getUser(),
                $model->getDivision()
            )
        );
    }
}
