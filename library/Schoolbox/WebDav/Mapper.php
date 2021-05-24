<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\WebDav;

use PDOStatement;
use Schoolbox\Campus;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\CampusMapper;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('webdav');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('uri', self::TYPE_STRING);
        $this->uses('path', self::TYPE_STRING);
        $this->uses('enabled', self::TYPE_BOOLEAN);
        $this->uses('username_prefix', self::TYPE_STRING);
        $this->uses('username_suffix', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->uses('role_type', self::TYPE_STRING);
        $this->joins('campus_id', CampusMapper::class, 'id', null, null, 'campus', 'campus');
        $this->joins('role_id', RoleMapper::class, 'id', null, null, 'role', 'role');
    }

    protected function getSelectString()
    {
        return "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('campus', 'c')},
                        {$this->getJoinColumnString('role', 'r')}
            FROM        {$this->getTable()}
            LEFT JOIN   {$this->getJoinSql('campus', '', 'c')}
            LEFT JOIN   {$this->getJoinSql('role', '', 'r')}
        ";
    }

    /**
     * @param PDOStatement $statement
     * @return GenericCollection|Model[]
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        foreach ($statement as $row) {
            $model = $this->hydrate($row);

            /** @var Campus $campus */
            $campus = $this->hydrateJoin($row, 'campus', 'c');
            $model->setCampus($campus);

            /** @var Role $role */
            $role = $this->hydrateJoin($row, 'role', 'r');
            $model->setRole($role);

            $collection->add($model);
        }

        return $collection;
    }
}
