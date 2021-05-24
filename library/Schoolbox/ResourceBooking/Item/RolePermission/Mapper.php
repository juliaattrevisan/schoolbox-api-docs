<?php

/**
 * Mapper for resource role permissions.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Item\RolePermission;

use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{

    public function mapping()
    {
        $this->hasTable('resource_role_permission');
        $this->hasModel('Schoolbox\\ResourceBooking\\Item\\RolePermission\\Model');
        $this->hasMap(
            array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'resource_id',
                'property' => 'resource',
                'type'     => self::TYPE_MODEL,
                'join'     => 'resource',
                'join_def' => array(
                    'mapper'    => '\\Schoolbox\\ResourceBooking\\Item\\Mapper',
                    'other_key' => 'id',
                ),
            )
        );
        $this->hasMap(
            array(
                'column'   => 'role_id',
                'property' => 'role',
                'type'     => self::TYPE_MODEL,
                'join'     => 'role',
                'join_def' => array(
                    'mapper'    => '\\Schoolbox\\RoleMapper',
                    'other_key' => 'id',
                ),
            )
        );
        $this->hasMap(
            array(
                'column'   => 'allowed',
                'property' => 'allowed',
                'type'     => self::TYPE_BOOLEAN,
            )
        );
    }
}
