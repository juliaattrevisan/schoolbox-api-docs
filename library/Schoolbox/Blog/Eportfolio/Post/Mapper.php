<?php
/**
 * Mapper for e-portfolio blog posts
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Blog\Eportfolio\Post;

use PDO;
use PDOStatement;
use Schoolbox\Blog\Eportfolio\Post\Model as EportfolioBlogModel;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('eportfolio_component_blog');
        $this->hasModel(EportfolioBlogModel::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('page_id', self::TYPE_INTEGER);
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->uses('heading', self::TYPE_STRING);
        $this->uses('body', self::TYPE_STRING);

        /** Joins */

        $this->hasJoin(
            'user',
            array(
                'mapper' => UserMapper::class,
                'this_key' => 'owner_id',
                'other_key' => 'id'
            )
        );
    }

    /**
     * Find all the blog entries for a homepage
     *
     * @param User $owner
     * @param $limit
     * @return GenericCollection
     */
    public function findForOwner(User $owner, $limit = 0)
    {
        $query =
            "
			SELECT
				{$this->getColumnString('b')},
				{$this->getJoinColumnString('user', 'u')}
			FROM `{$this->getTable()}` AS `b`
            INNER JOIN {$this->getJoinSql('user', '', 'u',true)}
            JOIN `eportfolio_page` p ON p.page_id = b.page_id
			WHERE `b`.`deleted_at` IS NULL
				AND `b`.`owner_id` = :owner_id
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
                'owner_id' => $owner->getId(),
            )
        );

        $c = $this->createCollection();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var User $user */
            $user = $this->hydrateJoin($r, 'user', 'u');

            /** @var EportfolioBlogModel $blog */
            $blog = $this->hydrate($r, 'b');
            $blog->setOwner($user);
            $c->add($blog);
        }

        return $c;
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
                {$this->getJoinColumnString('user', 'u')}
            FROM {$this->database->quoteIdentifier($this->table)}
            INNER JOIN {$this->getJoinSql('user', '', 'u',true)}
        ";
    }

    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param  PDOStatement $statement
     * @return GenericCollection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();
        foreach ($statement as $row) {
            $model = $this->hydrate($row);

            // set user and division only if retrieved properly
            $user = $this->hydrateJoin($row, 'user', 'u');
            if ($user instanceof User) {
                $model->setOwner($user);
            }
            $collection->add($model);
        }
        return $collection;
    }
}