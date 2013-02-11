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

    protected function mapping () {

        $this->hasTable('groups');
        $this->hasModel('\Schoolbox\Role');

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
                'type'       => Mapper::TYPE_BOOLEAN,
        ));

    }

}