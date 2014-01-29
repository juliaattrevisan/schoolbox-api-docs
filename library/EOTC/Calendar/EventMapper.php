<?php
/**
 * CalendarEventMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC\Calendar;
use Schoolbox;


class EventMapper extends Schoolbox\External\Mapper {
	const ACCESS_PERSONAL = 1;
	const ACCESS_GROUP = 2;
	const ACCESS_CAMPUS = 3;

	protected function mapping() {
		$this->hasTable('event');
		$this->hasModel('\\EOTC\\Calendar\Event');
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
			'type' => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column' => 'publish_date',
			'property' => 'publishDateTime',
			'type' => self::TYPE_DATETIME,
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
		$this->hasJoin('eventType', array(
			'mapper'    => '\\EOTC\\Calendar\\EventTypeMapper',
			'this_key'  => 'type',
			'other_key' => 'eventTypeID',
		));
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
		$eventTypeMapper = $this->getJoinMapper('eventType');

		$query = "
			SELECT
				{$this->getColumnString('e')},
				{$ownerMapper->getColumnString('o')},
				{$authorMapper->getColumnString('a')},
				{$eventTypeMapper->getColumnString('t')}
			FROM `{$this->table}` e
			LEFT JOIN {$this->getJoinSql('owner', 'e', 'o')}
			LEFT JOIN {$this->getJoinSql('author', 'e', 'a')}
			LEFT JOIN {$this->getJoinSql('eventType', 'e', 't')}
			WHERE e.id = :eventId
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":eventId", $identifier);
		$statement->execute();

		$row = $statement->fetch();

		/* Hydrate or die
		 */
		if ($row != false) {
			$eventItem = $this->hydrate($row, 'e');

			/* Owner
			 */
			$ownerItem = $ownerMapper->hydrate($row, 'o');
			$eventItem->setOwner($ownerItem);

			/* Author
			 */
			$authorItem = $authorMapper->hydrate($row, 'a');
			$eventItem->setAuthor($authorItem);

			/* Event Type
			 */
			$eventTypeItem = $eventTypeMapper->hydrate($row, 't');
			$eventItem->setEventType($eventTypeItem);

			/* Convert dates ;(
			 */
			$startDateTime = new \Alaress_Date_Format($eventItem->getStartDate() . ' ' . $eventItem->getStartTime(), 'y-m-d h:i:s');
			$eventItem->setStartDateTime($startDateTime);

			$endDateTime = new \Alaress_Date_Format($eventItem->getEndDate() . ' ' . $eventItem->getEndTime(), 'y-m-d h:i:s');
			$eventItem->setEndDateTime($endDateTime);

			$publishDateTime = new \Alaress_Date_Format($eventItem->getPublishDate(), 'y-m-d h:i:s');
			$eventItem->setPublishDateTime($publishDateTime);

			return $eventItem;
		}

		throw new \Exception('Instance of object not found');
	}
}