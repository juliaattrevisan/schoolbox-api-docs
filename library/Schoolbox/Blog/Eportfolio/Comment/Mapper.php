<?php

/**
 * Mapper for e-portfolio blog comments
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Blog\Eportfolio\Comment;

use PDO;
use PDOStatement;
use Schoolbox\Blog\Eportfolio\Post\Mapper as BlogMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;

/**
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping(): void
    {
        $this->hasTable('eportfolio_component_blog_comment');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('blog_id', BlogMapper::class, 'id', null, null, 'blog', 'blog');
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner', 'owner');
        $this->uses('body', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * @param Model $comment
     * @return GenericCollection|Model[]
     */
    public function findSiblings(Model $comment): GenericCollection
    {
        $pb = $this->getPredicateBuilder();
        $pb->equals('blog_id', $comment->getBlog()->getId())
           ->notEquals($this->getIdentifierColumn(), $comment->getId());
        return $this->findByPredicates($pb);
    }


    /**
     * This mapper's find* methods always hydrate the owner
     *
     * @return string
     */
    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('owner', 'o')},
                {$this->getJoinColumnString('owner.role', 'or')},
                {$this->getJoinColumnString('blog', 'b')}
            FROM {$this->database->getQuoter()->quoteIdentifier($this->table)}
            INNER JOIN {$this->getJoinSql('owner', '', 'o', true)}
            INNER JOIN {$this->getJoinSql('owner.role', 'o', 'or')}
            INNER JOIN {$this->getJoinSql('blog', '', 'b', true)}
        ";
    }

    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param PDOStatement $statement
     * @return GenericCollection|Model[]
     * @throws Exception
     */
    protected function hydrateSelect(PDOStatement $statement): array|GenericCollection
    {
        $collection = $this->createCollection();

        /** @var UserMapper $userMapper */
        $userMapper = $this->getJoinMapper('owner');

        /** @var BlogMapper $blogMapper */
        $blogMapper = $this->getJoinMapper('blog');

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $model = $this->hydrate($row);

            $owner = $userMapper->hydrate($row, 'o');

            /** @var \Schoolbox\Role $ownerRole */
            $ownerRole = $this->hydrateJoin($row, 'owner.role', 'or');

            $owner->setRole($ownerRole);
            $model->setOwner($owner);

            $blog = $blogMapper->hydrate($row, 'b');

            $model->setBlog($blog);

            $collection[$model->getId()] = $model;
        }

        return $collection;
    }
}
