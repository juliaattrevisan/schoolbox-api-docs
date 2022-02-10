<?php

/**
 * Mapper for user JWT ID in Schoolbox.
 *
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\User\Token;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 * @package Schoolbox\User\Token
 * @method Model createModel($idValue = null)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 */
class Mapper extends BaseMapper
{
    /**
     * {@inheritdoc}
     */
    public function mapping()
    {
        $this->hasTable('user_jwt_id');
        $this->hasModel(Model::class);
        $this->uses('jwt_id', self::TYPE_STRING, self::COLUMN_IDENTIFIER_UUID);
        $this->uses('expiry', self::TYPE_DATETIME);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
    }

    /**
     * @param User $user
     * @return Model
     * @throws BaseMapper\Exception
     */
    public function generateForUser(User $user): Model
    {
        // delete existing
        $existing = $this->findByColumn('user_id', $user->getId());
        $this->softDelete($existing);
        // create new
        $userJwtId = $this->createModel()->setUser($user);
        $this->save($userJwtId);
        // get the new one
        return $this->findOneByColumn('user_id', $user->getId());
    }
}
