<?php
/**
 * Mapper for e-portfolio blog posts
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Blog\Eportfolio\Post;

use PDO;
use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\User;
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
        $this->hasTable('eportfolio_component_blog');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('page_id', self::TYPE_INTEGER);
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner', 'owner');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->uses('heading', self::TYPE_STRING);
        $this->uses('body', self::TYPE_STRING);
    }

    /**
     * Find all the blog entries posted in a particular users ePortfolio
     *
     * @param User $user
     * @param int  $limit
     * @return GenericCollection|Model[]
     */
    public function findForPortfolioOwner(User $user, $limit = 0)
    {
        $query =
            "
			SELECT
				{$this->getColumnString('b')},
				{$this->getJoinColumnString('owner', 'u')}
			FROM `{$this->getTable()}` AS `b`
            INNER JOIN {$this->getJoinSql('owner', 'b', 'u', true)}
            JOIN `eportfolio_page` p ON p.page_id = b.page_id
			WHERE `b`.`deleted_at` IS NULL
				AND `p`.`uid` = :owner_id
				AND `p`.`deleted_at` IS NULL
			ORDER BY `b`.`created_at` DESC
		";

        if (is_numeric($limit) && $limit) {
            $limitValue = (int)$limit;

            $query .= " LIMIT {$limitValue}";
        }

        $stmt = $this->database->prepare($query);

        $stmt->execute(
            array(
                'owner_id' => $user->getId(),
            )
        );

        return $this->hydrateSelect($stmt);
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
