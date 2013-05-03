<?php
/**
 * Component_Eportfolio_BlogMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Component\Eportfolio;
use Schoolbox;

class BlogMapper extends Schoolbox\Mapper {

	/**
	 * Define column/property mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasTable('eportfolio_component_blog');
		$this->hasModel('\\Component\\Eportfolio\\Blog');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'page_id',
			'property'   => 'pageId',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'uid',
			'property'   => 'uid',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'title',
			'property'   => 'title',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'content',
			'property'   => 'content',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'created_at',
			'property'   => 'createdAt',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasMap(array(
			'column'     => 'updated_at',
			'property'   => 'updatedAt',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasMap(array(
			'column'     => 'deleted_at',
			'property'   => 'deletedAt',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasJoin('comments', array(
			'multiple'  => true,
			'mapper'    => '\\Component\\Eportfolio\\BlogCommentMapper',
			'this_key'  => 'id',
			'other_key' => 'blog_id',
		));

		parent::mapping();
	}

	/**
	 * Retrieves a collection of records ordered by a particular
	 * column in a particular order
	 *
	 * http://stackoverflow.com/questions/2542410/how-do-i-set-order-by-params-using-prepared-pdo-statement
	 *
	 * @param $args
	 * @return bool|Model
	 */
	public function findByOrder($args) {
		$comment_mapper = $this->getJoinMapper('comments');
		$author_mapper = $comment_mapper->getJoinMapper('author');

		$query = "
			SELECT
            	{$this->getColumnString('b')},
            	{$comment_mapper->getColumnString('c')},
            	{$author_mapper->getColumnString('a')}
            FROM `{$this->table}` b
            LEFT JOIN {$this->getJoinSql('comments', 'b', 'c')}
            LEFT JOIN {$comment_mapper->getJoinSql('author', 'c', 'a')}
            WHERE b.`{$args['identifierName']}` = :identifierValue
            ORDER BY b.{$args['column']} {$args['order']}
        ";

		$statement = $this->database->prepare($query);
		$statement->bindParam(':identifierValue', $args['identifierValue']);
		$statement->execute();

		/* Manually construct hierarchy of blog items and their comments
		 */
		$collection = new \Schoolbox\Collection();

		foreach ($statement as $row) {
			$blogItem = $this->hydrate($row, 'b');
			$commentItem = $comment_mapper->hydrate($row, 'c');

			/* Put it into the collection in the index with the same
			 * value as the blog id, if it doesn't already live there
			 */
			if (!is_a($collection->getOffset($blogItem->getId()), 'Component\Eportfolio\Blog')) {
				$collection->setOffset($blogItem->getId(), $blogItem);
			}

			/* If a comment was parsed from this $row, hydrate the author and
			 * put the comment with its parent blog
			 */
			if (is_numeric($commentItem->getId())) {
				$commentItem->setAuthor($author_mapper->hydrate($row, 'a'));

				$collection->getOffset($commentItem->getBlogId())->getComments()->add($commentItem);
			}
		}

		/* Reindex everything
		 *
		 * foreach() can't understand it when you don't reindex to zero-based array
		 */
		$collection->reindex();

		return $collection;
	}

	/**
	 * Retrieves a list of recently-created blogs
	 *
	 * @param $limit
	 * @return Collection
	 */
	public function findRecent($limit) {
		$query = "
            SELECT {$this->getColumnString()}
            FROM `{$this->table}`
            ORDER BY created_at DESC
            LIMIT :limit
		";

		$statement = $this->database->prepare($query);

		$statement->bindParam(':limit', $limit, \PDO::PARAM_INT);

		$statement->execute();

		$rowset = $statement->fetchAll();

		return $this->hydrateList($rowset);
	}
}