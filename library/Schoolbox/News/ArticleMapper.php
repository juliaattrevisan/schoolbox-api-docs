<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\News;


use PDO;
use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\Column\Model;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Searchable;
use Schoolbox\User;

class ArticleMapper extends Mapper implements Searchable {

	protected function mapping() {
		$this->hasTable('news_items');
		$this->hasModel('\\Schoolbox\\News\\Article');

		$this->hasMap(array(
			'column' => 'item_id',
			'property' => 'id',
			'type' => Mapper::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column' => 'item_topic_id',
			'property' => 'topic',
			'type' => Mapper::TYPE_MODEL,
			'join' => 'topic'
		));

		$this->hasMap(array(
			'column' => 'item_heading',
			'property' => 'heading',
			'type' => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column' => 'item_datetime_posted',
			'property' => 'postDate',
			'type' => Mapper::TYPE_DATETIME,
		));

		$this->hasMap(array(
			'column' => 'item_datetime_published',
			'property' => 'publishDate',
			'type' => Mapper::TYPE_DATE,
		));

		$this->hasMap(array(
			'column' => 'item_datetime_expires',
			'property' => 'expiryDate',
			'type' => Mapper::TYPE_DATE,
		));

		$this->hasMap(array(
			'column' => 'item_datetime_modified',
			'property' => 'modifiedDate',
			'type' => Mapper::TYPE_DATE,
		));

		$this->hasMap(array(
			'column' => 'item_body',
			'property' => 'body',
			'type' => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column' => 'item_deleted',
			'property' => 'deleted',
			'type' => Mapper::TYPE_BOOLEAN,
		));

		$this->hasMap(array(
			'column' => 'item_owner',
			'property' => 'owner',
			'type' => Mapper::TYPE_MODEL,
			'join' => 'owner'
		));

		// for storage, we use hash, not ID
		$hydrator = new Model();
		$hydrator->setIdentifierProperty('hash');
		$this->hasMap(array(
			'column' => 'item_image',
			'property' => 'image',
			'hydrator' => $hydrator,
			'join' => 'image'
		));

		$this->hasMap(array(
			// linked to a 'multi' join, does not have a 'column' declared
			'property' => 'roles',
			'type' => Mapper::TYPE_COLLECTION,
			'join' => 'roles'
		));

		$this->hasMap(array(
			// 'column' => null
			'property' => 'campuses',
			'type' => Mapper::TYPE_COLLECTION,
			'join' => 'campuses'
		));

		$this->hasMap(array(
			// 'column' => null
			'property' => 'users',
			'type' => Mapper::TYPE_COLLECTION,
			'join' => 'users'
		));

		$this->hasSoftDeleteBooleanField('item_deleted');


		$this->hasJoin('topic', array(
			'mapper'    => '\\Schoolbox\\News\\TopicMapper',
			'this_key'  => 'item_topic_id',
			'other_key' => 'newsTopicId',
		));

		$this->hasJoin('owner', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'item_owner',
			'other_key' => 'id',
		));

		$this->hasJoin('image', array(
			'mapper'    => '\\Schoolbox\\StorageMapper',
			'this_key'  => 'item_image',
			'other_key' => 'hash',
		));

		$this->hasJoin('roles', array(
			'multi' => array(
				'via_table' => 'newsGroup',
				'this_key' => 'id',
				'other_key' => 'gid'
			),
			'mapper' => '\\Schoolbox\\RoleMapper',
			'this_key' => 'item_id',
			'other_key' => 'id'
		));

		$this->hasJoin('campuses', array(
			'multi' => array(
				'via_table' => 'newsCampus',
				'this_key' => 'id',
				'other_key' => 'campus'
			),
			'mapper' => '\\Schoolbox\\CampusMapper',
			'this_key' => 'item_id',
			'other_key' => 'id'
		));

		$this->hasJoin('users', array(
			'multi' => array(
				'via_table' => 'newsUser',
				'this_key' => 'newsID',
				'other_key' => 'userID'
			),
			'mapper' => '\\Schoolbox\\UserMapper',
			'this_key' => 'item_id',
			'other_key' => 'id'
		));

	}


	/**
	 * Retrieves a model by id
	 *
	 * @throws Exception if no instance of model is found
	 * @throws Exception\Deleted if the model was found but has been deleted
	 * @param  int $identifierValue
	 * @return object
	 */
	public function findById($identifierValue) {
		$t = 0;

		// the main model we'll ultimately be populating
		$mainModel = null;

		/** @var Mapper[] $joinMappers */
		$joinMappers = array();
		$columnStrings = array($this->getColumnString("t$t"));
		$joinSqls = array();
		$propertyMap = array();
		$joinNames = array();

		$identifier = $this->getIdentifier();
		$identifierColumn = $identifier['column'];

		foreach($this->map as $map) {
			if(empty($map['join'])) {
				continue;
			}
			// increment our little `t` counter
			++$t;
			$name = $map['join'];

			$alias = "t$t";

			$propertyMap[$alias] = $map['property'];
			$joinNames[$alias] = $name;
			$joinMappers[$alias] = $this->getJoinMapper($name);
			if(!$joinMappers[$alias] instanceof Mapper) {
				continue;
			}
			$columnStrings[$alias] = $joinMappers[$alias]->getColumnString($alias);
			$joinSqls[$alias] = $this->getJoinSql($name, 't0', $alias);
		}

		$columns = implode(', ', $columnStrings);
		$joins = implode(' LEFT JOIN ', $joinSqls);
		if(strlen($joins)) {
			$joins = "LEFT JOIN $joins";
		}

		$stmt = $this->database->prepare("
			SELECT $columns FROM `{$this->getTable()}` t0 {$joins} WHERE `t0`.`{$identifierColumn}` = :id_value
		");

		$stmt->execute(array('id_value' => $identifierValue));


		// there will be more than one record returned by this query
		while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

			if(empty($mainModel)) {
				/** @var Article $mainModel */
                $mainModel = $this->hydrate($r, 't0');
                // set blank collections
                $mainModel->setRoles(new Collection());
                $mainModel->setCampuses(new Collection());
                $mainModel->setUsers(new Collection());
			}

			foreach($joinMappers as $alias => $mapper) {

				$subIdentifier = $mapper->getIdentifier();
				$subColumn = $subIdentifier['column'];

				$field = "$alias.$subColumn";

				if(is_null($r[$field])) {
					continue;
				}

				$entity = $mapper->hydrate($r, $alias);

				$property = $propertyMap[$alias];
				$joinName = $joinNames[$alias];

				// singular value
				if(empty($this->joins[$joinName]['multi'])) {

					$setEntity = $this->getSetMethodName($property);

					$mainModel->$setEntity($entity);
				}
				// multi-value...this is a bit circuitous...
				else {
					$getEntity = $this->getGetMethodName($property);

					if(!$mainModel->$getEntity() instanceof Collection) {
						$setEntity = $this->getSetMethodName($property);
						$mainModel->$setEntity(new Collection());
					}
					$mainModel->$getEntity()->add($entity);
				}
			}
		}

		return $mainModel;
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

		$campusMapper = $this->getJoinMapper('campuses');
		$userMapper = $this->getJoinMapper('users');
		$roleMapper = $this->getJoinMapper('roles');
		$topicMapper = $this->getJoinMapper('topic');

		$stmt = $this->database->prepare("
			SELECT
				{$this->getColumnString('a')},
				{$topicMapper->getColumnString('t')},
				{$userMapper->getColumnString('u')},
				{$campusMapper->getColumnString('c')},
				{$roleMapper->getColumnString('r')},
				MATCH(a.item_heading,a.item_body) AGAINST (:keyword) AS `score`
			FROM
				`{$this->getTable()}`
				LEFT JOIN {$this->getJoinSql('topic', 'a', 't')}
				LEFT JOIN {$this->getJoinSql('users', 'a', 'u')}
				LEFT JOIN {$this->getJoinSql('campuses', 'a', 'c')}
				LEFT JOIN {$this->getJoinSql('groups', 'a', 'r')}
			WHERE
				a.item_owner != :user_id
				AND a.item_deleted = 0
				AND MATCH(a.item_heading,a.item_body) AGAINST (:keyword)
				AND
				(
					a.item_datetime_published <= :publish_date
					AND ((c.id = :campus_id AND r.id = :role_id) OR u.id = :user_id)
					OR a.owner = :user_id
				)
			GROUP BY a.item_id
			ORDER BY newsTopic.newsTopicID ASC, news_items.item_datetime_posted DESC
		");

		$records = array();
		// if you're a superuser, set the publish date filter to a few hundred years in the future
		$time = $user->isSuperuser() ? time() + 3 * 1e10  : time();

		// iterate by campus
		foreach($user->getCampusCollection() as $campus) {
			/** @var Campus $campus */
			$stmt->execute(array(
				'keyword' => $keyword,
				'user_id' => $user->getId(),
				'role_id' => $user->getRole()->getId(),
				'campus_id' => $campus->getId(),
				'publish_date' => date('Y-m-d', $time)
			));

			$records = array_merge($records, $stmt->fetchAll(PDO::FETCH_ASSOC));
		}

		$collection = $this->hydrateList($records, 'a', function($mapper, $model, $row) {
			$map = array(
				't' => 'topic',
				'u' => 'users',
				'r' => 'roles',
				'c' => 'campuses',
			);

			foreach($map as $alias => $name) {
				/** @var Mapper $mapper */
				$mapper->setModelProperty($model, $name, $mapper->hydrateJoin($row, $name, $alias));
			}
		});

		$collection->slice($limit, $offset)->sortBy('publishDate', 'DESC');

		return $collection;
	}


	/**
	 * @param bool $archive
	 * @param string $topic
	 * @param int $year
	 * @param int $month
	 * @param int $offset
	 * @param int $limit
	 * @param bool $groupByTopic
	 * @return Collection|Collection[]
	 */
	public function getListing($archive = true, $topic = '', $year = 0, $month = 0, $offset = 0, $limit = 0, $groupByTopic = false) {

		$columns = array();

		if($topic) {
			$topicObject = TopicMapper::getInstance()->findByColumn('name', $topic)->first();
			$columns['item_topic_id'] = $topicObject->getId();
		}

		// filter by archive status, year, and month (as specified)
		$c = $this->findByColumnList($columns)->filterByCallback(
			function($item) use ($archive, $year, $month) {
				/** @var Article $item */
				$publishDate = $item->getPublishDate();
				$expireStamp = $item->getExpiryDate()->getUnixTimestamp();

				$yearMatch = $year ? $publishDate->getUsingFormat('Y') == $year : true;
				$monthMatch = $month ? $publishDate->getUsingFormat('m') == $month : true;

				return ($archive ? $expireStamp < time() : $expireStamp > time()) && $monthMatch && $yearMatch;
			}
		)->sortBy('publishDate', 'ASC');

		if($limit) {
			$c = $c->slice($limit, $offset);
		}

		if($groupByTopic) {
			$c = $c->groupByCallback(function($item) {
				/** @var $item Article */
				return $item->getTopic()->getId();
			});
		}

		return $c;
	}

}
