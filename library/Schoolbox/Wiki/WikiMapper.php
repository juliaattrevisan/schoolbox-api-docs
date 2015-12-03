<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Wiki;


use PDO;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Mapper;

/**
 * Class WikiMapper
 *
 * @package Schoolbox\Wiki
 * @method Wiki createModel()
 */
class WikiMapper extends Mapper implements FeedableMapperInterface {

	/**
	 * @return void
	 */
	protected function mapping() {
		$this->hasTable('wiki');
		$this->hasModel('\\Schoolbox\\Wiki\\Wiki');

		$this->hasMap(array(
			'column'     => 'wikiID',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'categoryID',
			'property'   => 'category',
			'type'       => self::TYPE_MODEL,
			'join'       => 'category'
		));

		$this->hasMap(array(
			'column'     => 'latestRevision',
			'property'   => 'latestRevision',
			'type'       => self::TYPE_MODEL,
			'join'       => 'revision'
		));

		$this->hasMap(array(
			'column'     => 'slug',
			'property'   => 'slug',
			'type'       => self::TYPE_STRING
		));

		$this->hasMap(array(
			'column'     => 'created',
			'property'   => 'created',
			'type'       => self::TYPE_DATETIME
		));

		$this->hasMap(array(
			'column'     => 'deleted',
			'property'   => 'deleted',
			'type'       => self::TYPE_BOOL_ENUM
		));

		$this->hasMap(array(
			'column'     => 'locked',
			'property'   => 'locked',
			'type'       => self::TYPE_BOOL_ENUM
		));

		$this->hasJoin('category', array(
			'mapper'    => '\\Schoolbox\\Resource\\Folder\\Mapper',
			'this_key'  => 'categoryID',
			'other_key' => 'id'
		));

		$this->hasJoin('revision', array(
			'mapper'    => '\\Schoolbox\\Wiki\\RevisionMapper',
			'this_key'  => 'latestRevision',
			'other_key' => 'revisionID'
		));

	}


	/**
	 * @param int $categoryId
	 * @param int $limit
	 * @param int $offset
	 * @param int $time
	 * @return Collection|Wiki[]
	 */
	public function findForFeed($categoryId, $limit, $offset = 0, $time = 0) {
		$time = date('Y-m-d H:i:s', $time ?: time());
		$limit = (int)$limit;
		$offset = (int)$offset;

		$stmt = $this->database->prepare("
			SELECT
				{$this->getColumnString('w')},
				{$this->getJoinColumnString('category', 'c')},
				{$this->getJoinColumnString('revision', 'r')},
				{$this->getJoinMapper('revision')->getJoinColumnString('user', 'u')}
			FROM
				`{$this->getTable()}` `w`
				LEFT JOIN {$this->getJoinSql('category', 'w', 'c')}
				LEFT JOIN {$this->getJoinSql('revision', 'w', 'r')}
				LEFT JOIN {$this->getJoinMapper('revision')->getJoinSql('user', 'r', 'u')}
			WHERE
				`c`.`id` = :cat_id
				AND `w`.`created` < :time
				AND `w`.`deleted` = 'false'
			ORDER BY `w`.`created` DESC
			LIMIT $offset, $limit
		");

		$stmt->execute(array(
			'cat_id' => $categoryId,
			'time'   => $time
		));

		$c = $this->createCollection();
		while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
			/** @var Wiki $wiki */
			$wiki = $this->hydrate($r, 'w');
			$wiki->setCategory($this->hydrateJoin($r, 'category', 'c'));
			$wiki->setLatestRevision($this->hydrateJoin($r, 'revision', 'r'));
			$wiki->getLatestRevision()->setUser($this->getJoinMapper('revision')->hydrateJoin($r, 'user', 'u'));
			$c->add($wiki);
		}
		return $c;
	}


} 
