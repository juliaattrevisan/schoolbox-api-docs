<?php
/**
 * Pastoral care access control. This allows the user to audience a pastoral care record to individual users, system
 * roles and user defined pastoral care groups.
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Access;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Pastoral\Group\Mapper as GroupMapper;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\UserMapper;
use Schoolbox\RoleMapper;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_access');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('pastoral_id', PastoralMapper::class, 'id', null, null, 'pastoral', 'pastoral');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->joins('role_id', RoleMapper::class, 'id', null, null, 'role', 'role');
        $this->joins('group_id', GroupMapper::class, 'id', null, null, 'group', 'group');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME);
        $this->hasSoftDeleteDateField('deleted_at');
    }

    /**
     * Return the first part of the string required to select from this mapper
     *
     * @throws BaseMapper\DefinitionException
     * @return string
     */
    protected function getSelectString()
    {
        return "
			SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('role', 'r')},
                        {$this->getJoinColumnString('user', 'u')},
                        {$this->getJoinColumnString('group', 'g')}
			FROM        {$this->getTable()}
            LEFT JOIN   {$this->getJoinSql('role', '', 'r')}
            LEFT JOIN   {$this->getJoinSql('user', '', 'u')}
            LEFT JOIN   {$this->getJoinSql('group', '', 'g')}
        ";
    }

    /**
     * @param PDOStatement $statement
     * @throws Exception
     * @return GenericCollection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $c = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {

            // The record must have a user, role or group specified

            if (empty($row['u.id']) && empty($row['r.id']) && empty($row['g.id'])) {
                // This record is invalid as it doesn't specify any of these, so don't bother adding to array
                continue;
            }

            /** @var Model $access */
            $access = $this->hydrate($row);

            if (!empty($row['u.id'])) {
                /** @noinspection PhpParamsInspection */
                $access->setUser($this->hydrateJoin($row, 'user', 'u'));
            }

            if (!empty($row['r.id'])) {
                /** @noinspection PhpParamsInspection */
                $access->setRole($this->hydrateJoin($row, 'role', 'r'));
            }

            if (!empty($row['g.id'])) {
                /** @noinspection PhpParamsInspection */
                $access->setGroup($this->hydrateJoin($row, 'group', 'g'));
            }

            $c->add($access);
        }

        return $c;
    }
}