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
use Schoolbox\Blog\Blog;

class CommentMapper extends Mapper implements FeedableMapperInterface {

	/**
	 * @return void
	 */
	protected function mapping() {
		$this->hasTable('blogComment');
		$this->hasModel('\\Schoolbox\\Blog\\Comment');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'bid',
			'property'   => 'blog',
			'type'       => self::TYPE_MODEL,
			'join'       => 'blog'
		));

		$this->hasMap(array(
			'column'     => 'owner',
			'property'   => 'owner',
			'type'       => self::TYPE_MODEL,
			'join'       => 'owner'
		));

		$this->hasMap(array(
			'column'     => 'created',
			'property'   => 'created',
			'type'       => self::TYPE_DATETIME
		));

		$this->hasMap(array(
			'column'     => 'deleted',
			'property'   => 'deleted',
			'type'       => self::TYPE_DATETIME
		));

		$this->hasMap(array(
			'column'     => 'body',
			'property'   => 'body',
			'type'       => self::TYPE_STRING
		));

		$this->hasJoin('blog', array(
			'mapper'    => '\\Schoolbox\\Blog\\BlogMapper',
			'this_key'  => 'bid',
			'other_key' => 'id',
		));

		$this->hasJoin('owner', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'owner',
			'other_key' => 'id',
		));

		// fake the 2nd-depth hydration
		$this->hasJoin('category', array(
			'mapper'    => '\\Schoolbox\\Resource\\Folder\\Mapper',
			'this_key'  => 'cid',
			'other_key' => 'id'
		));

		$this->hasSoftDeleteDateField('deleted');
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
			$comment->setBlog($this->hydrateJoin($r, 'blog', 'b'));
			$comment->getBlog()->setCategory($this->hydrateJoin($r, 'category','cat'));
			$comment->setOwner($this->hydrateJoin($r, 'owner', 'u'));
			$c->add($comment);
		}
		return $c;
	}

    /**
     * Find all the comments for a particular blog
     *
     * @param Blog $blog
     * @return Collection
     */
    public function findForBlog(Blog $blog) {
        $stmt = $this->database->prepare("
			SELECT
				{$this->getColumnString('c')},
				{$this->getJoinColumnString('owner', 'u')}
			FROM
				`{$this->getTable()}` `c`
            LEFT JOIN {$this->getJoinSql('owner', 'c', 'u')}
			WHERE
				`c`.`deleted` IS NULL
				AND `c`.bid = :blogId
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