<?php

/**
 * Mapper for normal (non-eportfolio) blog posts
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Blog\Homepage\Post;

use PDO;
use Schoolbox\Blog\Homepage\Post\Model as BlogModel;
use Schoolbox\Collection;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\IHomepageCleanable;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Searchable;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * @method Model createModel($idValue = null)
 */
class Mapper extends BaseMapper implements Searchable, FeedableMapperInterface, IHomepageCleanable
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('blog');
        $this->hasModel(BlogModel::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('folder_id', FolderMapper::class, 'id', null, null, 'folder', 'folder');
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner', 'owner');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->uses('heading', self::TYPE_STRING);
        $this->uses('body', self::TYPE_STRING);

        $this->joinsReverseMulti('blog_id', \Schoolbox\Blog\Homepage\Comment\Mapper::class, 'comments', 'comments');
        $this->joinsReverseMulti('blog_id', \Schoolbox\Blog\Homepage\Image\Mapper::class, 'images', 'images');

        $this->hasJoin(
            'perms',
            array(
                'mapper' => RoleAccessMapper::class,
                'this_key' => 'folder_id',
                'other_key' => 'cid'
            )
        );

        $this->hasJoin(
            'userAccess',
            array(
                'mapper' => UserAccessMapper::class,
                'this_key' => 'folder_id',
                'other_key' => 'sid'
            )
        );

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteComments']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteImages']);
    }


    /**
     * Performs a search with the given parameters, and returns a Collection of the resultant set.
     *
     * @param ArticleResolver $articleResolver
     * @param User            $user
     * @param string          $keyword
     * @param int             $offset
     * @param int             $limit
     * @param string          $sortProperty
     * @param string          $sortDirection
     * @return void
     * @throws BaseMapper\DefinitionException
     * @throws BaseMapper\Exception
     */
    public function search(
        ArticleResolver $articleResolver,
        $user,
        $keyword,
        $offset = 0,
        $limit = self::DEFAULT_LIMIT,
        $sortProperty = '',
        $sortDirection = 'DESC'
    ) {

        $sortBy = isset($this->map[$sortProperty]) ? $this->map[$sortProperty]['field'] : $this->getIdentifierColumn();
        $sortDir = strtoupper($sortDirection) == 'DESC' ? 'DESC' : 'ASC';

        $offset = (int)$offset;
        $limit = (int)$limit;

        $stmt = $this->database->prepare(
            "
			SELECT
				{$this->getColumnString('b')},
				{$this->getJoinMapper('owner')->getColumnString('u')},
				{$this->getJoinMapper('folder')->getColumnString('c')}
				MATCH(`heading`,`body`) AGAINST (:keyword) AS `score`
			FROM
				`{$this->getTable()}` `b`
				LEFT JOIN {$this->getJoinSql('owner', 'b', 'u')}
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
                    ->setOwner($this->hydrateJoin($r, 'owner', 'u'))
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
				{$this->getJoinColumnString('owner', 'u')},
				{$this->getJoinColumnString('folder', 'c')}
			FROM `{$this->getTable()}` AS `b`
				INNER JOIN {$this->getJoinSql('owner', 'b', 'u', true)}
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
            /** @var User $user */
            $user = $this->hydrateJoin($r, 'owner', 'u');

            /** @var FolderModel $folder */
            $folder = $this->hydrateJoin($r, 'folder', 'c');

            /** @var BlogModel $blog */
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
     * TODO: But I don't think this is used?
     *
     * @param FolderModel $folder
     * @param int|null $limit
     * @return Collection
     */
    public function findForCategory(FolderModel $folder, $limit = 0)
    {
        $query =
            "
			SELECT
				{$this->getColumnString('b')},
				{$this->getJoinColumnString('owner', 'u')}
			FROM `{$this->getTable()}` AS `b`
            LEFT JOIN {$this->getJoinSql('owner', 'b', 'u')}
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
                'folder_id' => $folder->getId(),
            )
        );

        $c = $this->createCollection();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var User $user */
            $user = $this->hydrateJoin($r, 'owner', 'u');

            /** @var BlogModel $blog */
            $blog = $this->hydrate($r, 'b');
            $blog->setOwner($user);
            $c->add($blog);
        }

        return $c;
    }

    /**
     * Soft-deletes blog for specified homepage
     *
     * @param int $cid
     * @return void
     */
    public function homepageClean($cid)
    {
        $this->deleteByColumn('folder_id', $cid);
    }

    /**
     * Soft deletes all comments that belongs to this blog model
     *
     * Called as a post soft-delete callback.
     *
     * @param Model $model
     * @throws DefinitionException If specified join mapper not found
     */
    public function softDeleteComments(Model $model)
    {
        $mapper = $this->getJoinMapper('comments');
        $mapper->softDelete($mapper->findByColumn('blog_id', $model->getId()));
    }

    /**
     * Soft deletes all images that belongs to this blog model
     *
     * Called as a post soft-delete callback.
     *
     * @param Model $model
     * @throws DefinitionException If specified join mapper not found
     */
    public function softDeleteImages(Model $model)
    {
        $mapper = $this->getJoinMapper('images');
        $mapper->softDelete($mapper->findByColumn('blog_id', $model->getId()));
    }
}
