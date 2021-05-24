<?php

/**
 * Mapper for e-portfolio blog posts
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Blog\Eportfolio\Post;

use PDO;
use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Eportfolio\PageMapper;

/**
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model findById($identifierValue)
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping(): void
    {
        $this->hasTable('eportfolio_component_blog');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('page_id', PageMapper::class, 'page_id', null, null, 'page', 'page');
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner', 'owner');
        $this->uses('heading', self::TYPE_STRING);
        $this->uses('body', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * Find all the blog entries posted in a particular users ePortfolio
     *
     * @param User $user
     * @param int  $limit
     * @return GenericCollection|Model[]
     * @throws BaseMapper\DefinitionException If join $name isn't on this mapper
     */
    public function findForPortfolioOwner(User $user, int $limit = 0): GenericCollection
    {
        $pb = $this->getPredicateBuilder();
        $pb->isNull('deleted_at')
           ->equals('p.uid', $user->getId())
           ->isNull('p.deleted_at');
        $whereClause = implode(' AND ', $pb->getWhere());

        $query = "
            {$this->getSelectString()}
            WHERE {$whereClause}
            ORDER BY `{$this->getTable()}`.`created_at` DESC";

        if (is_numeric($limit) && $limit) {
            $limitValue = (int)$limit;
            $query .= " LIMIT {$limitValue}";
        }

        $stmt = $this->database->prepare($query);
        $stmt->execute($pb->getParams());

        return $this->hydrateSelect($stmt);
    }

    /**
     * This mapper's find* methods always hydrate the owner and page
     *
     * @return string
     * @throws BaseMapper\DefinitionException If join $name isn't on this mapper
     */
    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('owner', 'u')},
                {$this->getJoinColumnString('page', 'p')}
            FROM `{$this->getTable()}`
            INNER JOIN {$this->getJoinSql('owner', '', 'u', true)}
            INNER JOIN {$this->getJoinSql('page', '', 'p', true)}
        ";
    }

    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param  PDOStatement $statement
     * @return GenericCollection|Model[]
     * @throws BaseMapper\DefinitionException If join $name isn't on this mapper
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        /** @var UserMapper $userMapper */
        $userMapper = $this->getJoinMapper('owner');
        /** @var PageMapper $pageMapper */
        $pageMapper = $this->getJoinMapper('page');
        $collection = $this->createCollection();

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $model = $this->hydrate($row);
            $model->setPage($pageMapper->hydrate($row, 'p'));
            $model->setOwner($userMapper->hydrate($row, 'u'));
            $collection[$model->getId()] = $model;
        }
        return $collection;
    }
}
