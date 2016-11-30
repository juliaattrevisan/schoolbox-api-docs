<?php
/**
 * SidMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Calendar;

use Schoolbox;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper;

class SidMapper extends Schoolbox\Mapper {

	/**
	 * Mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasTable('event_sid');
		$this->hasModel(Sid::class);

		$this->hasMap(array(
			'column' => 'event_id',
			'property' => 'eventId',
			'type' => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column' => 'sid',
			'property' => 'sid',
			'type' => self::TYPE_INTEGER,
		));
	}

	/**
	 * Purge existing sid assignations
	 *
	 * @param Event $event
	 */
	public function cleanse(Event $event) {
		$statement = $this->database->prepare("
			DELETE FROM `{$this->table}`
			WHERE `event_id` = :identifierValue
		");

		$statement->bindValue(':identifierValue', $event->getId());
		$statement->execute();
	}
}