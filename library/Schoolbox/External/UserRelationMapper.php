<?php
/**
 * Mapper for user relation objects - this refers to the relation between a
 * student and their parents/guardians.
 *
 * This is only used by the Schoolbox connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2013 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External;
use Schoolbox;

class UserRelationMapper extends Schoolbox\External\Mapper {

    /**
     * {@inheritdoc}
     */
    protected function mapping() {
        $this->hasTable('user_relation');
        $this->hasModel('\\Schoolbox\\External\\UserRelation');
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

}
