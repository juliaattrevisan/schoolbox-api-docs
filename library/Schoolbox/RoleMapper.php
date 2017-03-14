<?php
/**
 * Schoolbox user role mapper
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;

use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;

class RoleMapper extends Mapper {

    /* Enum in groups table
     */
    const ROLE_STAFF   = 'staff';
    const ROLE_PARENT  = 'parent';
    const ROLE_STUDENT = 'student';
    const ROLE_GUEST   = 'guest';

    /**
     * Setup mapper
     */
    protected function mapping() {

        $this->hasTable('groups');
        $this->hasModel(Role::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('group_type', self::TYPE_STRING, null, 'type');
        $this->uses('can_change_password', self::TYPE_BOOLEAN);
        $this->uses('digest_schedule', self::TYPE_STRING);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(
            self::CALLBACK_POSTSOFTDELETE,
            function (Role $role) {
                Pastoral\Access\Mapper::getInstance()->deleteByColumn('role_id', $role->getId());
            }
        );
    }
}