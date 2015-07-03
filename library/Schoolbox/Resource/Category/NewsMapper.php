<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Category;


use PDO;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Searchable;
use Schoolbox\User;
use Schoolbox\Permission\Access\UserAccess;

class NewsMapper extends Mapper implements Searchable, FeedableMapperInterface {

	/**
	 * @return void
	 */
	protected function mapping() {
		$this->hasTable('subject_news');
		$this->hasModel('\\Schoolbox\\Resource\\Category\\News');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'heading',
			'property'   => 'heading',
			'type'       => self::TYPE_STRING
		));

		$this->hasMap(array(
			'column'     => 'datetime_posted',
			'property'   => 'postDate',
			'type'       => self::TYPE_DATETIME
		));

		$this->hasMap(array(
			'column'     => 'datetime_published',
			'property'   => 'publishDate',
			'type'       => self::TYPE_DATE
		));

		$this->hasMap(array(
			'column'     => 'datetime_expires',
			'property'   => 'expiryDate',
			'type'       => self::TYPE_DATE
		));

		$this->hasMap(array(
			'column'     => 'body',
			'property'   => 'body',
			'type'       => self::TYPE_STRING
		));

		$this->hasMap(array(
			'column'     => 'deleted',
			'property'   => 'deleted',
			'type'       => self::TYPE_BOOLEAN
		));

		$this->hasMap(array(
			'column'     => 'code',
			'property'   => 'category',
			'type'       => self::TYPE_MODEL,
			'join'       => 'category'
		));

		$this->hasMap(array(
			'column'     => 'owner',
			'property'   => 'owner',
			'type'       => self::TYPE_MODEL,
			'join'       => 'owner'
		));

		$this->hasJoin('owner', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'owner',
			'other_key' => 'id'
		));

		$this->hasJoin('category', array(
			'mapper'    => '\\Schoolbox\\Resource\\CategoryMapper',
			'this_key'  => 'code',
			'other_key' => 'id'
		));

		$this->hasJoin('perms', array(
			'mapper'    => '\Schoolbox\Permission\Access\RoleAccessMapper',
			'this_key'  => 'code',
			'other_key' => 'cid'
		));

		$this->hasJoin('userAccess', array(
			'mapper'    => '\Schoolbox\Permission\Access\UserAccessMapper',
			'this_key'  => 'code',
			'other_key' => 'sid'
		));

		$this->hasSoftDeleteBooleanField('deleted');

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
	public function search($user, $keyword, $offset = 0, $limit = self::DEFAULT_LIMIT, $sortProperty = '', $sortDirection = 'DESC') {

		$sortBy = isset($this->map[$sortProperty]) ? $this->map[$sortProperty]['field'] : $this->identifier['field'];
		$sortDir = strtoupper($sortDirection) == 'DESC' ? 'DESC' : 'ASC';

		$offset = (int)$offset;
		$limit = (int)$limit;

		$stmt = $this->database->prepare("
			SELECT
				{$this->getColumnString('n')},
				{$this->getJoinMapper('owner')->getColumnString('u')},
				{$this->getJoinMapper('category')->getColumnString('c')},
				MATCH(`heading`,`body`) AGAINST (:keyword) AS `score`
			FROM
				`{$this->getTable()}` `n`
				LEFT JOIN {$this->getJoinSql('owner',      'n', 'u')}
				INNER JOIN {$this->getJoinSql('category',   'n', 'c')}
				LEFT JOIN {$this->getJoinSql('perms',      'n', 'p')}
					AND p.gid = :role_id
					AND p.type > :min_access
				LEFT JOIN {$this->getJoinSql('userAccess', 'n', 'a')}
					AND a.service = :service
					AND a.uid = :user_id
			WHERE
				(p.cid OR a.sid)
				AND MATCH(`heading`,`body`) AGAINST (:keyword) AS `score`
				AND (
					n.datetime_published <= :publish_date
					OR u.id = :user_id
				)
				AND n.deleted = FALSE
			ORDER BY $sortBy $sortDir
			LIMIT $offset, $limit
		");

		$stmt->execute(array(
			'keyword'      => $keyword,
			'role_id'      => $user->getRole()->getId(),
			'min_access'   => UserAccess::ACCESS_READ,
			'service'      => UserAccess::SERVICE_HOMEPAGE,
			'user_id'      => $user->getId(),
			'publish_date' => date('Y-m-d H:i:s')
		));

		$collection = $this->createCollection();

		while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$collection->add(
				$this->hydrate($r, 'n')
					->setOwner($this->hydrateJoin($r,'owner','u'))
					->setCategory($this->hydrateJoin($r, 'category', 'c'))
			);
		}

		return $collection;
	}


	/**
	 * @param $categoryId
	 * @param $limit
	 * @param int $offset
	 * @param int $time
	 * @return Collection|News[]
	 */
	public function findForFeed($categoryId, $limit, $offset = 0, $time = 0) {
		$time = date('Y-m-d H:i:s', $time ?: time());
		$limit = (int)$limit;
		$offset = (int)$offset;

		$stmt = $this->database->prepare("
			SELECT
				{$this->getColumnString('n')},
				{$this->getJoinColumnString('category', 'c')},
				{$this->getJoinColumnString('owner', 'u')}
			FROM
				`{$this->getTable()}` `n`
				INNER JOIN {$this->getJoinSql('category', 'n', 'c')}
				LEFT JOIN {$this->getJoinSql('owner', 'n', 'u')}
			WHERE
				`n`.`deleted` = FALSE
				AND `n`.`datetime_published` < :time
				AND `n`.`code` = :cat_id
			ORDER BY `n`.`datetime_published` DESC
			LIMIT $offset, $limit
		");

		$stmt->execute(array(
			'time' => $time,
			'cat_id' => $categoryId
		));

		$c = $this->createCollection();
		while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
			/** @var News $news */
			$news = $this->hydrate($r, 'n');
			$news->setOwner($this->hydrateJoin($r, 'owner', 'u'));
			$news->setCategory($this->hydrateJoin($r, 'category', 'c'));
			$c->add($news);
		}
		return $c;
	}

}