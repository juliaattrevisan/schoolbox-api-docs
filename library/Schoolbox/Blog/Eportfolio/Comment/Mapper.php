<?php
/**
 * Mapper for e-portfolio blog comments
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Blog\Eportfolio\Comment;

use PDO;
use Schoolbox\Blog\Eportfolio\Comment\Model as EportfolioBlogCommentModel;
use Schoolbox\Blog\Eportfolio\Post\Mapper as EportfolioBlogMapper;
use Schoolbox\Blog\Eportfolio\Post\Model as EportfolioBlogModel;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Mapper extends BaseMapper implements FeedableMapperInterface
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('eportfolio_component_blog_comment');
        $this->hasPrototype(new EportfolioBlogCommentModel);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('blog_id', EportfolioBlogMapper::class, 'id', null, null, 'blog');
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner');
        $this->uses('body', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->hasSoftDeleteDateField('deleted_at');

        $this->hasJoin(
            'owner',
            array(
                'mapper' => UserMapper::class,
                'this_key' => 'owner_id',
                'other_key' => 'id'
            )
        );

        $this->hasJoin(
            'blog',
            array(
                'mapper' => EportfolioBlogMapper::class,
                'this_key' => 'blog',
                'other_key' => 'id'
            )
        );
    }

    /**
     * @param int $ownerId
     * @param int $limit
     * @param int $offset
     * @param int $time
     * @return Collection|EportfolioBlogModel[]
     */
    public function findForFeed($folderId, $limit, $offset = 0, $time = 0)
    {
        $time = date('Y-m-d H:i:s', $time ? : time());

        $limit = (int)$limit;
        $offset = (int)$offset;

        $stmt = $this->database->prepare(
            "
			SELECT
				{$this->getColumnString('bc')},
				{$this->getJoinColumnString('owner', 'u')},
				{$this->getJoinColumnString('blog', 'b')}
			FROM `{$this->getTable()}` AS `bc`
				LEFT JOIN {$this->getJoinSql('owner', 'bc', 'u')}
				INNER JOIN {$this->getJoinSql('blog', 'bc', 'b')}
			WHERE `bc`.`deleted_at` IS NULL
				AND `b`.`folder_id` = :folder_id
				AND `bc`.`created_at` < :time
			ORDER BY `bc`.`created_at` DESC
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
            $owner = $this->hydrateJoin($r, 'owner', 'u');

            /** @var FolderModel $folder */
            $folder = $this->hydrateJoin($r, 'folder', 'c');

            /** @var EportfolioBlogModel $blog */
            $blog = $this->hydrate($r, 'b');
            $blog->setOwner($owner);
            $blog->setFolder($folder);
            $c->add($blog);
        }

        return $c;
    }

    /**
     * Find all the comments for a particular blog
     *
     * @param EportfolioBlogModel $blog
     * @return Collection
     */
    public function findForBlog(EportfolioBlogModel $blog) {
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

            /** @var EportfolioBlogCommentModel $comment */
            $comment = $this->hydrate($r, 'c');
            $comment->setOwner($user);
            $c->add($comment);
        }

        return $c;
    }
}