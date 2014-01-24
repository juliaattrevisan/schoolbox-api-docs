<?php
/**
 * CalendarEventMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;
use Schoolbox;


class CalendarEventMapper extends Schoolbox\External\Mapper {

	protected function mapping() {
		$this->hasTable('event');
		$this->hasModel('\\EOTC\\CalendarEvent');
		$this->hasMap(array(
			'column' => 'id',
			'property' => 'id',
			'type' => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column' => 'series_id',
			'property' => 'seriesId',
			'type' => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column' => 'owner',
			'property' => 'owner',
			'type' => self::TYPE_MODEL,
			'join' => 'owner',
		));
		$this->hasMap(array(
			'column' => 'author_id',
			'property' => 'author',
			'type' => self::TYPE_MODEL,
			'join' => 'author',
		));
		$this->hasMap(array(
			'column' => 'access',
			'property' => 'access',
			'type' => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column' => 'sid',
			'property' => 'sid',
			'type' => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column' => 'type',
			'property' => 'type',
			'type' => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column' => 'note',
			'property' => 'note',
			'type' => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column' => 'detail',
			'property' => 'detail',
			'type' => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column' => 'location',
			'property' => 'location',
			'type' => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'   => 'startDate',
			'property' => 'startDate',
			'type'     => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'   => 'startTime',
			'property' => 'startTime',
			'type'     => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'   => 'endDate',
			'property' => 'endDate',
			'type'     => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'   => 'endTime',
			'property' => 'endTime',
			'type'     => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column' => 'attendance',
			'property' => 'attendance',
			'type' => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column' => 'completed',
			'property' => 'completed',
			'type' => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column' => 'allday',
			'property' => 'allDay',
			'type' => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column' => 'publish_date',
			'property' => 'publishDate',
			'type' => self::TYPE_DATE,
		));
		$this->hasJoin('owner', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'owner',
			'other_key' => 'id',
		));
		$this->hasJoin('author', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'author_id',
			'other_key' => 'id',
		));

		parent::mapping();
	}

	/**
	 * Custom findById
	 *
	 * @param mixed $identifier
	 * @return object|Model
	 * @throws \Exception
	 */
	public function findById($identifier) {
		$ownerMapper = $this->getJoinMapper('owner');
		$authorMapper = $this->getJoinMapper('author');

		$query = "
			SELECT
				{$this->getColumnString('e')},
				{$ownerMapper->getColumnString('o')},
				{$authorMapper->getColumnString('a')}
			FROM `{$this->table}` e
			LEFT JOIN {$this->getJoinSql('owner', 'e', 'o')}
			LEFT JOIN {$this->getJoinSql('author', 'e', 'a')}
			WHERE e.id = :eventId
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":eventId", $identifier);
		$statement->execute();

		$row = $statement->fetch();

		if ($row != false) {
			$ownerItem = $ownerMapper->hydrate($row, 'o');
			$authorItem = $authorMapper->hydrate($row, 'a');

			$eventItem = $this->hydrate($row, 'e');
			$eventItem->setOwner($ownerItem);
			$eventItem->setAuthor($authorItem);

			return $eventItem;
		}

		throw new \Exception('Instance of object not found');
	}
}