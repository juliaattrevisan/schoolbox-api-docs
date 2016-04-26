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
use Schoolbox\UserMapper;
use Schoolbox\Blog\Blog\Model as BlogModel;
use Schoolbox\Blog\Blog\Mapper as BlogMapper;
use Schoolbox\Blog\Comment\Model as BlogCommentModel;
use Schoolbox\Resource\Folder\Model as Folder;

class CommentMapper extends Mapper implements FeedableMapperInterface {

	/**
	 * @return void
	 */
	protected function mapping() {
		$this->hasTable('blogComment');
		$this->hasPrototype(new BlogCommentModel);
		$this->hasSoftDeleteDateField('deleted_at');
		$this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
		$this->uses('body', self::TYPE_STRING);
		$this->uses('created_at', self::TYPE_DATETIME);
		$this->uses('deleted_at', self::TYPE_DATETIME);
		$this->joins('blog_id', BlogMapper::class, 'id', null, null, 'blog');
		$this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner');

		// Joins

		$this->hasJoin('blog', array(
			'mapper'    => '\\Schoolbox\\Blog\\BlogMapper',
			'this_key'  => 'blog_id',
			'other_key' => 'id',
		));

		$this->hasJoin('owner', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'owner',
			'other_key' => 'id',
		));

		// Fake the 2nd-depth hydration

		$this->hasJoin('category', array(
			'mapper'    => '\\Schoolbox\\Resource\\Folder\\Mapper',
			'this_key'  => 'cid',
			'other_key' => 'id'
		));
	}

	/**
	 * @param int $categoryId
	 * @param int $limit
	 * @param int $offset
	 * @param int $time
	 * @return Collection|Comment[]
	 */
	public function findForFeed($categoryId, $limit, $offset = 0, $time = 0) {
		$time = date('Y-m-d H:i:s', $time ?: time());

		$limit = (int)$limit;
		$offset = (int)$offset;

		$stmt = $this->database->prepare("
			SELECT
				{$this->getColumnString('c')},
				{$this->getJoinColumnString('blog', 'b')},
				{$this->getJoinColumnString('category', 'cat')},
				{$this->getJoinColumnString('owner', 'u')}
			FROM
				`{$this->getTable()}` `c`
				LEFT JOIN {$this->getJoinSql('blog',  'c', 'b')}
				LEFT JOIN {$this->getJoinSql('category', 'b', 'cat')}
				LEFT JOIN {$this->getJoinSql('owner', 'c', 'u')}
			WHERE
				`c`.`deleted` IS NULL
				AND `b`.`deleted` IS NULL
				AND `c`.`created` < :time
				AND `b`.`cid` = :cat_id
			ORDER BY `c`.`created` DESC
			LIMIT $offset, $limit
		");

		$stmt->execute(array(
			'time'   => $time,
			'cat_id' => $categoryId
		));

		$c = $this->createCollection();

		while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
			/** @var Comment $comment */
			$comment = $this->hydrate($r, 'c');

			/** @var BlogModel $blog */
			$blog = $this->hydrateJoin($r, 'blog', 'b');
			
			/** @var Folder $folder */
			$folder = $this->hydrateJoin($r, 'category','cat');
			
			$comment->setBlog($blog);
			$comment->getBlog()->setFolder($folder);
			$comment->setOwner($this->hydrateJoin($r, 'owner', 'u'));
			$c->add($comment);
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
				`c`.`deleted` IS NULL
				AND `c`.blog_id = :blogId
			ORDER BY `c`.`created` DESC
		");

        $stmt->execute(array(
            'blogId' => $blog->getId()
        ));

        $c = $this->createCollection();

        while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var Comment $comment */
            $comment = $this->hydrate($r, 'c');
            $comment->setOwner($this->hydrateJoin($r, 'owner', 'u'));
            $c->add($comment);
        }

        return $c;
    }
}