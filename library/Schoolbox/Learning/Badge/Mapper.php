<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Badge;

use PDOStatement;
use Schoolbox\File\Mimetype;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 *
 * @method Model findById($id)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method Model findOneByColumnList($columnList, array $options = [])
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model createModel($idValue = null)
 *
 * @package Schoolbox\Learning\Badge\Category
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('badge');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('body', self::TYPE_STRING);
        $this->joins('image_hash', StorageMapper::class, 'hash', null, null, 'image', 'image');
        $this->joins('category_id', Category\Mapper::class, 'id', null, null, 'category', 'category');
        $this->joins('created_by', UserMapper::class, 'id', null, null, 'creator', 'creator');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->joins('updated_by', UserMapper::class, 'id', null, null, 'updator', 'updator');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * @return string
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('image', 'i')},
                {$this->getJoinColumnString('image.allowedFileExtension', 'm')},
                {$this->getJoinColumnString('category', 'bc')},
                {$this->getJoinColumnString('creator', 'c')},
                {$this->getJoinColumnString('creator.role', 'cr')},
                {$this->getJoinColumnString('updator', 'u')},
                {$this->getJoinColumnString('updator.role', 'ur')}
            FROM {$this->getDatabase()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('image', '', 'i', true)}
            LEFT JOIN {$this->getJoinSql('image.allowedFileExtension', 'i', 'm', true)}
            INNER JOIN {$this->getJoinSql('category', '', 'bc', true)}
            INNER JOIN {$this->getJoinSql('creator', '', 'c', true)}
            INNER JOIN {$this->getJoinSql('creator.role', 'c', 'cr')}
            INNER JOIN {$this->getJoinSql('updator', '', 'u', true)}
            INNER JOIN {$this->getJoinSql('updator.role', 'u', 'ur')}
        ";
    }


    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param PDOStatement $statement
     * @return GenericCollection|Model[]
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        foreach ($statement as $row) {
            $model = $this->hydrate($row);

            /* Set the Category
             */
            $category = $this->hydrateJoin($row, 'category', 'bc');

            if ($category instanceof Category\Model) {
                $model->setCategory($category);
            }

            /* Set the creator and also their role
             */
            $creator = $this->hydrateJoin($row, 'creator', 'c');

            if ($creator instanceof User) {
                $role = $this->hydrateJoin($row, 'creator.role', 'cr');

                if ($role instanceof Role) {
                    $creator->setRole($role);
                }

                $model->setCreator($creator);
            }

            /* Set the updator and also their role
             */
            $updator = $this->hydrateJoin($row, 'updator', 'u');

            if ($updator instanceof User) {
                $role = $this->hydrateJoin($row, 'updator.role', 'ur');

                if ($role instanceof Role) {
                    $updator->setRole($role);
                }

                $model->setUpdator($updator);
            }

            /* Add the optional image and its mime
             */
            $image = $this->hydrateJoin($row, 'image', 'i');

            if ($image instanceof \Schoolbox\Resource\Storage\Model) {
                $mime = $this->hydrateJoin($row, 'image.allowedFileExtension', 'm');

                if ($mime instanceof Mimetype) {
                    $image->setMimetype($mime);
                }

                $model->setImage($image);
            }

            $collection->add($model);
        }

        return $collection;
    }
}
