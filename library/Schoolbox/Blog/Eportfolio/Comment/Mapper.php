<?php
/**
 * Mapper for e-portfolio blog comments
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Blog\Eportfolio\Comment;

use PDO;
use PDOStatement;
use Schoolbox\Blog\Eportfolio;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;

/**
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{

    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('eportfolio_component_blog_comment');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('blog_id', Eportfolio\Post\Mapper::class, 'id', null, null, 'blog', 'blog');
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
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('owner', 'u')}
            FROM {$this->database->quoteIdentifier($this->table)}
            INNER JOIN {$this->getJoinSql('owner', '', 'u', true)}
        ";
    }

    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param  PDOStatement $statement
     * @return GenericCollection|Model[]
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var UserMapper $userMapper */
        $userMapper = $this->getJoinMapper('owner');
        $collection = $this->createCollection();

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $model = $this->hydrate($row);
            $model->setOwner($userMapper->hydrate($row, 'u'));
            $collection[$model->getId()] = $model;
        }
        return $collection;
    }
}
