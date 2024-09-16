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
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\IHomepageCleanable;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * @method Model createModel($idValue = null)
 */
class Mapper extends BaseMapper implements FeedableMapperInterface, IHomepageCleanable
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
            [
                'mapper'    => RoleAccessMapper::class,
                'this_key'  => 'folder_id',
                'other_key' => 'cid',
            ]
        );

        $this->hasJoin(
            'userAccess',
            [
                'mapper'    => UserAccessMapper::class,
                'this_key'  => 'folder_id',
                'other_key' => 'sid',
            ]
        );

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteComments']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteImages']);
        $this->registerPersistSearchIndexCallbacks();
    }

    /**
     * @inheritDoc
     */
    public function getSelectString()
    {
        return "SELECT
                {$this->getColumnString()},
                {$this->getJoinMapper('owner')->getColumnString('u')},
                {$this->getJoinColumnString('owner.pronoun', 'op')}
        ";
    }

    /**
     * @inheritDoc
     */
    public function getFromString()
    {
        return "FROM
                `{$this->getTable()}`
                JOIN {$this->getJoinSql('owner', 'blog', 'u', true)}
                LEFT JOIN {$this->getJoinSql('owner.pronoun', 'u', 'op')}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(\PDOStatement $statement)
    {
        // Return pastoral records fully hydrated by default

        $collection = $this->createCollection();

        foreach ($statement as $row) {
            /** @var Model $model */
            $model = $this->hydrate($row);

            /** @var User $owner */
            $owner = $this->hydrateJoin($row, 'owner', 'u');
            $owner->setPronoun($this->hydrateJoin($row, 'owner.pronoun', 'op'));
            $model->setOwner($owner);

            $collection->add($model);
        }

        return $collection;
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
        $time = date('Y-m-d H:i:s', $time ?: time());

        $limit  = (int)$limit;
        $offset = (int)$offset;

        $stmt = $this->database->prepare(
            "
			SELECT
				{$this->getColumnString('b')},
				{$this->getJoinColumnString('owner', 'u')},
                {$this->getJoinColumnString('owner.pronoun', 'op')},
				{$this->getJoinColumnString('folder', 'c')}
			FROM `{$this->getTable()}` AS `b`
				INNER JOIN {$this->getJoinSql('owner', 'b', 'u', true)}
			    LEFT JOIN {$this->getJoinSql('owner.pronoun', 'u', 'op')}
				INNER JOIN {$this->getJoinSql('folder', 'b', 'c')}
			WHERE `b`.`deleted_at` IS NULL
				AND `b`.`folder_id` = :folder_id
				AND `b`.`created_at` < :time
			ORDER BY `b`.`created_at` DESC
			LIMIT $offset, $limit
		"
        );

        $stmt->execute(
            [
                'folder_id' => $folderId,
                'time'      => $time,
            ]
        );

        $c = $this->createCollection();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var User $user */
            $user = $this->hydrateJoin($r, 'owner', 'u');
            $user->setPronoun($this->hydrateJoin($r, 'owner.pronoun', 'op'));

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
     */
    public function softDeleteImages(Model $model)
    {
        $mapper = $this->getJoinMapper('images');
        $mapper->softDelete($mapper->findByColumn('blog_id', $model->getId()));
    }
}
