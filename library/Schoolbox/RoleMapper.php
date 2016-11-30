<?php
/**
 * Description of the file and what its purpose is
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;
use Schoolbox;

class RoleMapper extends Mapper {

    /* Enum in groups table
     */
    const ROLE_STAFF   = 'staff';
    const ROLE_PARENT  = 'parent';
    const ROLE_STUDENT = 'student';
    const ROLE_GUEST   = 'guest';

    protected function mapping () {

        $this->hasTable('groups');
        $this->hasModel(Role::class);

        $this->hasMap(array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => Mapper::TYPE_INTEGER,
                'identifier' => true
        ));

        $this->hasMap(array(
                'column'     => 'name',
                'property'   => 'name',
                'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
                'column'     => 'group_type',
                'property'   => 'type',
                'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
                'column'     => 'can_change_password',
                'property'   => 'canChangePassword',
                'type'       => Mapper::TYPE_BOOL_ENUM,
        ));

        $this->hasMap(array(
            'column'   => 'digest_schedule',
            'property' => 'digestSchedule',
            'type'     => self::TYPE_STRING,
        ));
    }

}