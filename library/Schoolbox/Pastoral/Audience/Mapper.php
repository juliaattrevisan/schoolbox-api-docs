<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Pastoral\Audience;

use PDO;
use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Pastoral\Group\Mapper as GroupMapper;
use Schoolbox\Pastoral\SubType\Mapper as SubTypeMapper;
use Schoolbox\Pastoral\Type\Mapper as TypeMapper;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Pastoral;

/**
 * Pastoral care pastoral type audience defaults
 *
 * This allows the user to set a default audience for a pastoral type/subtype
 *
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{
    // Define record access levels
    private const ACCESS_DENY    = Pastoral\Access\Mapper::ACCESS_DENY;     // Deny trumps all other access to allow exclusion of a user/group/role
    private const ACCESS_PUBLIC  = Pastoral\Access\Mapper::ACCESS_PUBLIC;
    private const ACCESS_PRIVATE = Pastoral\Access\Mapper::ACCESS_PRIVATE;

    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_audience');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('access', self::TYPE_INTEGER);
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->joins('role_id', RoleMapper::class, 'id', null, null, 'role', 'role');
        $this->joins('group_id', GroupMapper::class, 'id', null, null, 'group', 'group');
        $this->joins('type_id', TypeMapper::class, 'id', null, null, 'type', 'type');
        $this->joins('subtype_id', SubTypeMapper::class, 'id', null, null, 'subtype', 'subtype');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * Return the first part of the string required to select from this mapper
     *
     * @throws BaseMapper\DefinitionException
     * @return string
     */
    protected function getSelectString()
    {
        // Intentionally does not join onto soft-deleted strong entities
        // (e.g. An access is for a User, and user is soft-deleted)

        return "
			SELECT      {$this->getColumnString()},
			            {$this->getJoinColumnString('type', 't')},
                        {$this->getJoinColumnString('subtype', 'st')},
                        {$this->getJoinColumnString('role', 'r')},
                        {$this->getJoinColumnString('user', 'u')},
                        {$this->getJoinColumnString('group', 'g')}
			FROM        {$this->getTable()}
            JOIN        {$this->getJoinSql('type', '', 't')}
            LEFT JOIN   {$this->getJoinSql('subtype', '', 'st')}
            LEFT JOIN   {$this->getJoinSql('role', '', 'r')}
            LEFT JOIN   {$this->getJoinSql('user', '', 'u')}
            LEFT JOIN   {$this->getJoinSql('group', '', 'g')}
        ";
    }

    /**
     * @param PDOStatement $statement
     *
     * @throws Exception
     * @return GenericCollection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $results = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            // The record must have a user, role or group specified

            if (empty($row['u.id']) && empty($row['r.id']) && empty($row['g.id'])) {
                continue;
            }

            $access = $this->hydrate($row);

            $access->setType($this->hydrateJoin($row, 'type', 't'));
            if (!empty($row['st.id'])) {
                $access->setSubtype($this->hydrateJoin($row, 'subtype', 'st'));
            }

            // Hydrate user

            if (!empty($row['u.id'])) {
                /** @var User $user */
                $user = $this->hydrateJoin($row, 'user', 'u');
                $user->setRole = $this->getJoinMapper('role')->findByIdWithFallback($user->getRole()->getId());
                $access->setUser($user);
            }

            // Hydrate role

            if (!empty($row['r.id'])) {
                $access->setRole($this->hydrateJoin($row, 'role', 'r'));
            }

            // Hydrate group

            if (!empty($row['g.id'])) {
                $access->setGroup($this->hydrateJoin($row, 'group', 'g'));
            }

            $results->add($access);
        }

        return $results;
    }

    /**
     * @param int $typeId
     * @param int $subtypeId
     * @throws Exception
     */
    public function hardDeleteByType($typeId, $subtypeId): void
    {
        $oldPerms = $this->findByColumnList(['type_id' => $typeId, 'subtype_id' => $subtypeId], ['deleted' => true]);

        if (\count($oldPerms) > 0) {
            foreach ($oldPerms as $oldie) {
                $this->hardDelete($oldie);
            }
        }

    }
}
