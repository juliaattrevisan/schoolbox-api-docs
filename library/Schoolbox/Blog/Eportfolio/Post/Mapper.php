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
use Schoolbox\Blog\Eportfolio\Post\Model as EportfolioBlogModel;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Searchable;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Mapper extends BaseMapper implements Searchable, FeedableMapperInterface
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('eportfolio_component_blog');
        $this->hasPrototype(new EportfolioBlogModel);

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
     * Returns a list of years and months which have blog posts
     *
     * @param int $ownerId
     * @return array
     */
    public function getOccupiedDateList($ownerId)
    {
        $stmt = $this->database->prepare(
            "
			SELECT YEAR(`created_at`) as 'year', `created_at`, MONTH(`created`) as 'month'
			FROM {$this->getTable()}
			WHERE `cid` = :cid
			GROUP BY `year` DESC, `month` DESC
		"
        );

        $stmt->execute(array('cid' => $ownerId));
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
				{$this->getJoinMapper('owner')->getColumnString('c')}
				MATCH(`heading`,`body`) AGAINST (:keyword) AS `score`
			FROM
				`{$this->getTable()}` `b`
				LEFT JOIN {$this->getJoinSql('user', 'b', 'u')}
				INNER JOIN {$this->getJoinSql('owner', 'b', 'c')}
				LEFT JOIN {$this->getJoinSql('perms', 'b', 'p')}
					AND p.gid = :role_id
					AND p.type > :min_access
				LEFT JOIN {$this->getJoinSql('userAccess', 'b', 'a')}
					AND a.service = :service
					AND a.uid = :user_id
			WHERE
				(p.cid OR a.sid)
				AND b.deleted_at IS NULL
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
                    ->setFolder($this->hydrateJoin($r, 'owner', 'c'))
            );
        }

    }

    /**
     * @param int $ownerId
     * @param int $limit
     * @param int $offset
     * @param int $time
     * @return Collection|EportfolioBlogModel[]
     */
    public function findForFeed($ownerId, $limit, $offset = 0, $time = 0)
    {
        $time = date('Y-m-d H:i:s', $time ? : time());

        $limit = (int)$limit;
        $offset = (int)$offset;

        $stmt = $this->database->prepare(
            "
			SELECT
				{$this->getColumnString('b')},
				{$this->getJoinColumnString('user', 'u')},
				{$this->getJoinColumnString('owner', 'c')}
			FROM `{$this->getTable()}` AS `b`
				LEFT JOIN {$this->getJoinSql('user', 'b', 'u')}
				INNER JOIN {$this->getJoinSql('owner', 'b', 'c')}
			WHERE `b`.`deleted_at` IS NULL
				AND `b`.`owner_id` = :owner_id
				AND `b`.`created_at` < :time
			ORDER BY `b`.`created_at` DESC
			LIMIT $offset, $limit
		"
        );

        $stmt->execute(
            array(
                'owner_id' => $ownerId,
                'time' => $time
            )
        );

        $c = $this->createCollection();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var User $user */
            $user = $this->hydrateJoin($r, 'user', 'u');

            /** @var FolderModel $folder */
            $folder = $this->hydrateJoin($r, 'owner', 'c');

            /** @var EportfolioBlogModel $blog */
            $blog = $this->hydrate($r, 'b');
            $blog->setOwner($user);
            $blog->setFolder($folder);
            $c->add($blog);
        }

        return $c;
    }

    /**
     * Find all the blog entries for a homepage
     *
     * @param User $owner
     * @param $limit
     * @return Collection
     */
    public function findForOwner(User $owner, $limit = 0)
    {
        $query =
            "
			SELECT
				{$this->getColumnString('b')},
				{$this->getJoinColumnString('user', 'u')}
			FROM `{$this->getTable()}` AS `b`
            LEFT JOIN {$this->getJoinSql('user', 'b', 'u')}
			WHERE `b`.`deleted_at` IS NULL
				AND `b`.`owner_id` = :owner_id
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

}