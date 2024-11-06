<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\BulkImport\Access;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\BulkImport\Mapper as BulkImportMapper;
use Schoolbox\Pastoral\Group\Mapper as GroupMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;

/**
 * Mapper for pastoral bulk import access.
 */
class Mapper extends BaseMapper
{
    /**
     * This mapper shares many fields with the base pastoral access mapper.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_bulk_import_access');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins(
            'pastoral_bulk_import_id',
            BulkImportMapper::class,
            'id',
            null,
            null,
            'pastoralBulkImport',
            'pastoralBulkImport',
        );

        // Principals this access applies to
        $this->joins('group_id', GroupMapper::class, 'id', null, null, 'group', 'group');
        $this->joins('role_id', RoleMapper::class, 'id', null, null, 'role', 'role');
        $this->uses(
            'role_type',
            self::TYPE_STRING,
            self::COLUMN_ENUM,
            'roleType',
            [Role::GUEST, Role::STUDENT, Role::PARENT, Role::STAFF, null],
        );
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');

        // Level of access granted
        $this->uses('access', self::TYPE_INTEGER);

        // Limitations on access
        $this->uses('match_campus', self::TYPE_BOOLEAN);
        $this->uses('comments_allowed', self::TYPE_BOOLEAN);

        // Standard created/deleted
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }
}
