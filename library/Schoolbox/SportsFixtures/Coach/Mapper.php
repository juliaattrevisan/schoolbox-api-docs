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
use Schoolbox\UserMapper;
use Schoolbox\User as User;

/**
 * The mapper for a sports fixtures coach.
 *
 * @method Model createModel()
 * @package Schoolbox\SportsFixtures\Coach
 */
class Mapper extends BaseMapper
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
//            'team_id',
//            'coach_id',
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
                if ($user instanceof User) {
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
}
