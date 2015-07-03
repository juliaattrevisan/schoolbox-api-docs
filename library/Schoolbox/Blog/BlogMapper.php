<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Blog;


use PDO;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Searchable;
use Schoolbox\User;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model as CategoryModel;

class BlogMapper extends Mapper implements Searchable, FeedableMapperInterface
{

    /**
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('blog');
        $this->hasModel('\\Schoolbox\\Blog\\Blog');

        $this->hasMap(
            array(
                'column' => 'id',
                'property' => 'id',
                'type' => self::TYPE_INTEGER,
                'identifier' => true
            )
        );

        $this->hasMap(
            array(
                'column' => 'cid',
                'property' => 'category',
                'type' => self::TYPE_MODEL,
                'join' => 'category'
            )
        );

        $this->hasMap(
            array(
                'column' => 'owner',
                'property' => 'owner',
                'type' => self::TYPE_MODEL,
                'join' => 'user'
            )
        );

        $this->hasMap(
            array(
                'column' => 'created',
                'property' => 'created',
                'type' => self::TYPE_DATETIME
            )
        );

        $this->hasMap(
            array(
                'column' => 'deleted',
                'property' => 'deleted',
                'type' => self::TYPE_DATETIME
            )
        );

        $this->hasMap(
            array(
                'column' => 'heading',
                'property' => 'heading',
                'type' => self::TYPE_STRING
            )
        );

        $this->hasMap(
            array(
                'column' => 'body',
                'property' => 'body',
                'type' => self::TYPE_STRING
            )
        );


        $this->hasJoin(
            'category',
            array(
                'mapper' => '\\Schoolbox\\Resource\\CategoryMapper',
                'this_key' => 'cid',
                'other_key' => 'id'
            )
        );

        $this->hasJoin(
            'user',
            array(
                'mapper' => '\\Schoolbox\\UserMapper',
                'this_key' => 'owner',
                'other_key' => 'id'
            )
        );

        $this->hasJoin(
            'perms',
            array(
                'mapper' => '\\Schoolbox\\Permission\\Access\\RoleAccessMapper',
                'this_key' => 'cid',
                'other_key' => 'cid'
            )
        );

        $this->hasJoin(
            'userAccess',
            array(
                'mapper' => '\\Schoolbox\\Permission\\Access\\UserAccessMapper',
                'this_key' => 'cid',
                'other_key' => 'sid'
            )
        );

        $this->hasSoftDeleteDateField('deleted');
    }


    /**
     * Returns a list of years and months which have blog posts
     *
     * @param int $categoryId
     * @return array
     */
    public function getOccupiedDateList($categoryId)
    {
        $stmt = $this->database->prepare(
            "
			SELECT YEAR(`created`) as 'year', `created`, MONTH(`created`) as 'month'
			FROM {$this->getTable()}
			WHERE `cid` = :cid
			GROUP BY `year` DESC, `month` DESC
		"
        );

        $stmt->execute(array('cid' => $categoryId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Performs a search with the given parameters, and returns a Collection of the resultant set.
     *
     * @param User $user
     * @param string $keyword
     * @param int $offset
     * @param int $limit
     * @param string $sortProperty
     * @param string $sortDirection
     * @return Collection
     */
    public function search(
        $user,
        $keyword,
        $offset = 0,
        $limit = self::DEFAULT_LIMIT,
        $sortProperty = '',
        $sortDirection = 'DESC'
    ) {

        $sortBy = isset($this->map[$sortProperty]) ? $this->map[$sortProperty]['field'] : $this->identifier['field'];
        $sortDir = strtoupper($sortDirection) == 'DESC' ? 'DESC' : 'ASC';

        $offset = (int)$offset;
        $limit = (int)$limit;

        $stmt = $this->database->prepare(
            "
			SELECT
				{$this->getColumnString('b')},
				{$this->getJoinMapper('user')->getColumnString('u')},
				{$this->getJoinMapper('category')->getColumnString('c')}
				MATCH(`heading`,`body`) AGAINST (:keyword) AS `score`
			FROM
				`{$this->getTable()}` `b`
				LEFT JOIN {$this->getJoinSql('user', 'b', 'u')}
				INNER JOIN {$this->getJoinSql('category', 'b', 'c')}
				LEFT JOIN {$this->getJoinSql('perms', 'b', 'p')}
					AND p.gid = :role_id
					AND p.type > :min_access
				LEFT JOIN {$this->getJoinSql('userAccess', 'b', 'a')}
					AND a.service = :service
					AND a.uid = :user_id
			WHERE
				(p.cid OR a.sid)
				AND b.deleted IS NULL
				AND MATCH(`heading`,`body`) AGAINST (:keyword)
			ORDER BY $sortBy $sortDir
			LIMIT $offset, $limit
		"
        );

        $stmt->execute(
            array(
                'keyword' => $keyword,
                'role_id' => $user->getRole()->getId(),
                'service' => UserAccess::SERVICE_HOMEPAGE,
                'min_access' => UserAccess::ACCESS_READ,
                'user_id' => $user->getId()
            )
        );

        $collection = $this->createCollection();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $collection->add(
                $this->hydrate($r, 'b')
                    ->setOwner($this->hydrateJoin($r, 'user', 'u'))
                    ->setCategory($this->hydrateJoin($r, 'category', 'c'))
            );
        }

    }

    /**
     * @param int $categoryId
     * @param int $limit
     * @param int $offset
     * @param int $time
     * @return Collection|Blog[]
     */
    public function findForFeed($categoryId, $limit, $offset = 0, $time = 0)
    {
        $time = date('Y-m-d H:i:s', $time ? : time());

        $limit = (int)$limit;
        $offset = (int)$offset;

        $stmt = $this->database->prepare(
            "
			SELECT
				{$this->getColumnString('b')},
				{$this->getJoinColumnString('user', 'u')},
				{$this->getJoinColumnString('category', 'c')}
			FROM `{$this->getTable()}` AS `b`
				LEFT JOIN {$this->getJoinSql('user', 'b', 'u')}
				INNER JOIN {$this->getJoinSql('category', 'b', 'c')}
			WHERE `b`.`deleted` IS NULL
				AND `b`.`cid` = :cat_id
				AND `b`.`created` < :time
			ORDER BY `b`.`created` DESC
			LIMIT $offset, $limit
		"
        );

        $stmt->execute(
            array(
                'cat_id' => $categoryId,
                'time' => $time
            )
        );

        $c = $this->createCollection();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var Blog $blog */
            $blog = $this->hydrate($r, 'b');
            $blog->setOwner($this->hydrateJoin($r, 'user', 'u'));
            $blog->setCategory($this->hydrateJoin($r, 'category', 'c'));
            $c->add($blog);
        }

        return $c;
    }

    /**
     * Find all the blog entries for a homepage
     *
     * @param CategoryModel $category
     * @param $limit
     * @return Collection
     */
    public function findForCategory(CategoryModel $category, $limit = 0)
    {
        $query =
            "
			SELECT
				{$this->getColumnString('b')},
				{$this->getJoinColumnString('user', 'u')}
			FROM `{$this->getTable()}` AS `b`
            LEFT JOIN {$this->getJoinSql('user', 'b', 'u')}
			WHERE `b`.`deleted` IS NULL
				AND `b`.`cid` = :cat_id
			ORDER BY `b`.`created` DESC
		";

        if (is_numeric($limit) && $limit) {
            $limitValue = (int)$limit;

            $query .= " LIMIT {$limitValue}";
        }

        $stmt = $this->database->prepare($query);

        $stmt->execute(
            array(
                'cat_id' => $category->getId(),
            )
        );

        $c = $this->createCollection();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var Blog $blog */
            $blog = $this->hydrate($r, 'b');
            $blog->setOwner($this->hydrateJoin($r, 'user', 'u'));
            $c->add($blog);
        }

        return $c;
    }

}