<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
use Schoolbox\UserMapper;
use UnexpectedValueException;
use InvalidArgumentException;

/**
 * Pastoral care pastoral type audience defaults
 *
 * This allows the user to set a default audience for a pastoral type/subtype
 *
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection|Model[] createCollection($contents = [])
 * @method GenericCollection|Model[] findByColumnList($columnList, $options = [])
 * @method GenericCollection|Model[] findAll($options = [])
 */
class Mapper extends BaseMapper
{

    /**
     * Setup mapper
     *
     * @throws InvalidArgumentException
     */
    protected function mapping(): void
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

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'validateRelationships']);
    }

    /**
     * Return the first part of the string required to select from this mapper
     *
     * @throws BaseMapper\DefinitionException
     * @return string
     */
    protected function getSelectString(): string
    {
        return "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('type', 't')},
                        {$this->getJoinColumnString('subtype', 'st')},
                        {$this->getJoinColumnString('role', 'r')},
                        {$this->getJoinColumnString('user', 'u')},
                        {$this->getJoinColumnString('user.role', 'ur')},
                        {$this->getJoinColumnString('group', 'g')}
            FROM        {$this->getTable()}
            LEFT JOIN   {$this->getJoinSql('type', '', 't', true)}
            LEFT JOIN   {$this->getJoinSql('subtype', '', 'st', true)}
            LEFT JOIN   {$this->getJoinSql('role', '', 'r', true)}
            LEFT JOIN   {$this->getJoinSql('user', '', 'u', true)}
            LEFT JOIN   {$this->getJoinSql('user.role', 'u', 'ur', true)}
            LEFT JOIN   {$this->getJoinSql('group', '', 'g', true)}
        ";
    }


    /**
     * @param PDOStatement $statement
     *
     * @throws Exception
     * @throws BaseMapper\DefinitionException
     * @return GenericCollection
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        $results = $this->createCollection();

        /**
         * @var UserMapper    $userMapper
         * @var RoleMapper    $roleMapper
         * @var TypeMapper    $typeMapper
         * @var SubTypeMapper $subTypeMapper
         * @var GroupMapper   $pGroupMapper
         */
        $userMapper    = $this->getJoinMapper('user');
        $roleMapper    = $this->getJoinMapper('role');
        $typeMapper    = $this->getJoinMapper('type');
        $subTypeMapper = $this->getJoinMapper('subtype');
        $pGroupMapper  = $this->getJoinMapper('group');

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $access = $this->hydrate($row);

            if (!empty($row['st.id'])) {
                $access->setSubtype($subTypeMapper->hydrate($row, 'st'));
            }
            if (!empty($row['t.id'])) {
                $access->setType($typeMapper->hydrate($row, 't'));
            }

            // Hydrate user
            if (!empty($row['u.id'])) {
                $user = $userMapper->hydrate($row, 'u');
                $user->setRole($roleMapper->hydrate($row, 'ur'));
                $access->setUser($user);
            }

            // Hydrate role
            if (!empty($row['r.id'])) {
                $access->setRole($roleMapper->hydrate($row, 'r'));
            }

            // Hydrate group
            if (!empty($row['g.id'])) {
                $access->setGroup($pGroupMapper->hydrate($row, 'g'));
            }

            $results[$access->getId()] = $access;
        }

        return $results;
    }


    /**
     * Ensures the model has correct relationships
     *
     * @param Model $model
     * @return bool
     * @throws UnexpectedValueException If invalid relationship combination detected
     */
    protected function validateRelationships(Model $model): bool
    {
        $errors = [];
        if ($model->getType() && $model->getSubtype()) {
            $errors[] = "Audience cannot be for both a Type and SubType";
        } elseif (!$model->getType() && !$model->getSubtype()) {
            $errors[] = "Audience must have either a Type and SubType";
        }

        $count = (int)$model->getUser() + (int)$model->getRole() + (int)$model->getGroup();
        if (!$count) {
            $errors[] = "Audience must have either a User, Role, or Group";
        } elseif ($count > 1) {
            $errors[] = "Audience must be for only one of User, Role, or Group";
        }

        if (\count($errors)) {
            throw new UnexpectedValueException(\implode("\n", $errors));
        }

        return true;
    }
}
