<?php

/**
 * Mapper for user relation objects - this refers to the relation between a
 * student and their parents/guardians.
 *
 * This is only used by the Schoolbox connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External;

use Schoolbox;
use Schoolbox\User;

class UserRelationMapper extends Schoolbox\Mapper
{

    /**
     * {@inheritdoc}
     */
    protected function mapping()
    {
        $this->hasTable('user_relation');
        $this->hasModel(UserRelation::class);
        $this->hasMap(array(
            'column'   => 'user_id',
            'property' => 'user',
            'type'     => self::TYPE_MODEL,
            'join'     => 'user',
        ));
        $this->hasMap(array(
            'column'   => 'relation_id',
            'property' => 'relation',
            'type'     => self::TYPE_MODEL,
            'join'     => 'relation',
        ));
        $this->hasMap(array(
            'column'   => 'relation_type',
            'property' => 'relationType',
            'type'     => self::TYPE_INTEGER,
        ));
        $this->hasJoin(
            'user',
            array(
                'mapper'    => '\\Schoolbox\\UserMapper',
                'this_key'  => 'user_id',
                'other_key' => 'id',
            )
        );
        $this->hasJoin(
            'relation',
            array(
                'mapper'    => '\\Schoolbox\\UserMapper',
                'this_key'  => 'relation_id',
                'other_key' => 'id',
            )
        );
    }

    /**
     * Clears all relation entries for a user.
     *
     * @param User $user
     *
     * @return int
     */
    public function deleteForUser(User $user)
    {
        $query = "
            DELETE FROM `{$this->getTable()}`
            WHERE `user_id` = :id OR `relation_id` = :id
        ";
        $statement = $this->database->prepare($query);
        $statement->execute(array('id' => $user->getId()));
        return $statement->rowCount();
    }
}
