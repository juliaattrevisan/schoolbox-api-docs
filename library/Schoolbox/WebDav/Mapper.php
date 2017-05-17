<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\WebDav;

use PDOStatement;
use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
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
        $this->uses('deleted_at', self::TYPE_DATETIME);
        $this->uses('role_type', self::TYPE_STRING);
        $this->joins('campus_id', CampusMapper::class, 'id', null, null, 'campus', 'campus');
        $this->joins('role_id', RoleMapper::class, 'id', null, null, 'role', 'role');
        $this->hasSoftDeleteDateField('deleted_at');
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
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = new Collection();

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
