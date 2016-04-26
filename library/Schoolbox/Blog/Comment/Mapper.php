<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Blog\Comment;

use PDO;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Blog\Comment\Model as BlogCommentModel;
use Schoolbox\Blog\Blog\Mapper as BlogMapper;
use Schoolbox\Blog\Blog\Model as BlogModel;
use Schoolbox\Mapper\Searchable;
use Schoolbox\Feed\FeedableMapperInterface;

class Mapper extends BaseMapper implements Searchable, FeedableMapperInterface
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('blog_comment');
        $this->hasPrototype(new BlogCommentModel);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('blog_id', BlogMapper::class, 'id', null, null, 'blog');
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner');
        $this->uses('body', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME);
        $this->uses('deleted_at', self::TYPE_DATETIME);
        $this->hasSoftDeleteDateField('deleted_at');

        $this->hasJoin(
            'owner',
            array(
                'mapper' => '\\Schoolbox\\UserMapper',
                'this_key' => 'owner_id',
                'other_key' => 'id'
            )
        );

        $this->hasJoin(
            'blog',
            array(
                'mapper' => '\\Schoolbox\\Blog\\Blog\\Mapper',
                'this_key' => 'blog',
                'other_key' => 'id'
            )
        );
    }

    /**
     * Returns a list of years and months which have blog posts
     *
     * @param int $folderId
     * @return array
     */
    public function getOccupiedDateList($folderId)
    {
        $stmt = $this->database->prepare(
            "
			SELECT YEAR(`created_at`) as 'year', `created_at`, MONTH(`created_at`) as 'month'
			FROM {$this->getTable()}
			WHERE `folder_id` = :folder_id
			GROUP BY `year` DESC, `month` DESC
		"
        );

        $stmt->execute(array('folder_id' => $folderId));
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
				{$this->getJoinMapper('folder')->getColumnString('c')}
				MATCH(`heading`,`body`) AGAINST (:keyword) AS `score`
			FROM
				`{$this->getTable()}` `b`
				LEFT JOIN {$this->getJoinSql('user', 'b', 'u')}
				INNER JOIN {$this->getJoinSql('folder', 'b', 'c')}
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
                    ->setFolder($this->hydrateJoin($r, 'folder', 'c'))
            );
        }

    }

    /**
     * @param int $folderId
     * @param int $limit
     * @param int $offset
     * @param int $time
     * @return Collection|BlogModel[]
     */
    public function findForFeed($folderId, $limit, $offset = 0, $time = 0)
    {
        $time = date('Y-m-d H:i:s', $time ? : time());

        $limit = (int)$limit;
        $offset = (int)$offset;

        $stmt = $this->database->prepare(
            "
			SELECT
				{$this->getColumnString('b')},
				{$this->getJoinColumnString('user', 'u')},
				{$this->getJoinColumnString('folder', 'c')}
			FROM `{$this->getTable()}` AS `b`
				LEFT JOIN {$this->getJoinSql('user', 'b', 'u')}
				INNER JOIN {$this->getJoinSql('folder', 'b', 'c')}
			WHERE `b`.`deleted_at` IS NULL
				AND `b`.`folder_id` = :folder_id
				AND `b`.`created_at` < :time
			ORDER BY `b`.`created_at` DESC
			LIMIT $offset, $limit
		"
        );

        $stmt->execute(
            array(
                'folder_id' => $folderId,
                'time' => $time
            )
        );

        $c = $this->createCollection();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var User $owner */
            $owner = $this->hydrateJoin($r, 'user', 'u');

            /** @var FolderModel $folder */
            $folder = $this->hydrateJoin($r, 'folder', 'c');

            /** @var BlogModel $blog */
            $blog = $this->hydrate($r, 'b');
            $blog->setOwner($owner);
            $blog->setFolder($folder);
            $c->add($blog);
        }

        return $c;
    }

    /**
     * Find all the blog entries for a homepage
     *
     * @param FolderModel $folderId
     * @param $limit
     * @return Collection
     */
    public function findForCategory(FolderModel $folderId, $limit = 0)
    {
        $query =
            "
			SELECT
				{$this->getColumnString('b')},
				{$this->getJoinColumnString('user', 'u')}
			FROM `{$this->getTable()}` AS `b`
            LEFT JOIN {$this->getJoinSql('user', 'b', 'u')}
			WHERE `b`.`deleted_at` IS NULL
				AND `b`.`folder_id` = :folder_id
			ORDER BY `b`.`created_at` DESC
		";

        if (is_numeric($limit) && $limit) {
            $limitValue = (int)$limit;

            $query .= " LIMIT {$limitValue}";
        }

        $stmt = $this->database->prepare($query);

        $stmt->execute(
            array(
                'folder_id' => $folderId->getId(),
            )
        );

        $c = $this->createCollection();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var User $user */
            $user = $this->hydrateJoin($r, 'user', 'u');

            /** @var BlogModel $blog */
            $blog = $this->hydrate($r, 'b');
            $blog->setOwner($user);
            $c->add($blog);
        }

        return $c;
    }

    /**
     * Find all the comments for a particular blog
     *
     * @param BlogModel $blog
     * @return Collection
     */
    public function findForBlog(BlogModel $blog) {
        $stmt = $this->database->prepare("
			SELECT
				{$this->getColumnString('c')},
				{$this->getJoinColumnString('owner', 'u')}
			FROM
				`{$this->getTable()}` `c`
            LEFT JOIN {$this->getJoinSql('owner', 'c', 'u')}
			WHERE
				`c`.`deleted_at` IS NULL
				AND `c`.blog_id = :blogId
			ORDER BY `c`.`created_at` DESC
		");

        $stmt->execute(array(
            'blogId' => $blog->getId()
        ));

        $c = $this->createCollection();

        while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var User $user */
            $user = $this->hydrateJoin($r, 'owner', 'u');

            /** @var BlogCommentModel $comment */
            $comment = $this->hydrate($r, 'c');
            $comment->setOwner($user);
            $c->add($comment);
        }

        return $c;
    }
}