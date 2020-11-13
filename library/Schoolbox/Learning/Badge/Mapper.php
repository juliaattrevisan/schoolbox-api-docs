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
 * @method Model[] findByColumnList($columnList, array $options = [])
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
        $this->joins('created_by', UserMapper::class, 'id', null, null, 'createdBy', 'createdBy');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->joins('updated_by', UserMapper::class, 'id', null, null, 'updatedBy', 'updatedBy');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        /* A bunch of Curriculum Nodes
         */
        $this->joinsVia(
            'curriculum_badge',
            \Schoolbox\Curriculum\Node\Mapper::class,
            'id',
            'curriculum_node_id',
            'badge_id',
            null,
            null,
            null,
            'curriculumNodes'
        );
    }


    /**
     * @return string
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        $curriculumNodeMapper = \Schoolbox\Curriculum\Node\Mapper::getInstance();

        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('image', 'i')},
                {$this->getJoinColumnString('image.allowedFileExtension', 'm')},
                {$this->getJoinColumnString('category', 'bc')},
                {$curriculumNodeMapper->getColumnString('cn')},
                {$this->getJoinColumnString('createdBy', 'c')},
                {$this->getJoinColumnString('createdBy.role', 'cr')},
                {$this->getJoinColumnString('updatedBy', 'u')},
                {$this->getJoinColumnString('updatedBy.role', 'ur')}
            FROM {$this->getDatabase()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('image', '', 'i', true)}
            LEFT JOIN {$this->getJoinSql('image.allowedFileExtension', 'i', 'm', true)}
            INNER JOIN {$this->getJoinSql('category', '', 'bc', true)}
            LEFT JOIN {$this->getJoinSql('curriculum_badge', '', 'cn')}
            INNER JOIN {$this->getJoinSql('createdBy', '', 'c', true)}
            INNER JOIN {$this->getJoinSql('createdBy.role', 'c', 'cr')}
            INNER JOIN {$this->getJoinSql('updatedBy', '', 'u', true)}
            INNER JOIN {$this->getJoinSql('updatedBy.role', 'u', 'ur')}
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
        $curriculumNodeMapper = \Schoolbox\Curriculum\Node\Mapper::getInstance();
        $collection = $this->createCollection();
        $curriculumNodeCache = [];

        foreach ($statement as $row) {
            $badgeId = $row['id'];

            if (!$collection->offsetExists($badgeId)) {
                $collection[$badgeId] = $this->hydrate($row);
                $collection[$badgeId]->setCurriculumNodes($curriculumNodeMapper->createCollection());
            }

            /* Set the Category
             */
            $category = $this->hydrateJoin($row, 'category', 'bc');

            if ($category instanceof Category\Model) {
                $collection[$badgeId]->setCategory($category);
            }

            /* Set all the curriculum nodes
             */
            $curriculumNodeId = $row['cn.id'];

            if ($curriculumNodeId) {
                if (!array_key_exists($curriculumNodeId, $curriculumNodeCache)) {
                    $curriculumNodeCache[$curriculumNodeId] = $curriculumNodeMapper->hydrate($row, 'cn');
                }

                $collection[$badgeId]->getCurriculumNodes()->setOffset($curriculumNodeId, $curriculumNodeCache[$curriculumNodeId]);
            }

            /* Set the creator and also their role
             */
            $createdBy = $this->hydrateJoin($row, 'createdBy', 'c');

            if ($createdBy instanceof User) {
                $role = $this->hydrateJoin($row, 'createdBy.role', 'cr');

                if ($role instanceof Role) {
                    $createdBy->setRole($role);
                }

                $collection[$badgeId]->setCreatedBy($createdBy);
            }

            /* Set the updator and also their role
             */
            $updatedBy = $this->hydrateJoin($row, 'updatedBy', 'u');

            if ($updatedBy instanceof User) {
                $role = $this->hydrateJoin($row, 'updatedBy.role', 'ur');

                if ($role instanceof Role) {
                    $updatedBy->setRole($role);
                }

                $collection[$badgeId]->setUpdatedBy($updatedBy);
            }

            /* Add the optional image and its mime
             */
            $image = $this->hydrateJoin($row, 'image', 'i');

            if ($image instanceof \Schoolbox\Resource\Storage\Model) {
                $mime = $this->hydrateJoin($row, 'image.allowedFileExtension', 'm');

                if ($mime instanceof Mimetype) {
                    $image->setMimetype($mime);
                }

                $collection[$badgeId]->setImage($image);
            }
        }

        return $collection;
    }
}
