<?php
/**
 * CalendarEventTypeMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;
use Schoolbox;


class CalendarEventTypeMapper extends Schoolbox\External\Mapper {

	protected function mapping() {
		$this->hasTable('luEventType');
		$this->hasModel('\\EOTC\\CalendarEventType');
		$this->hasMap(array(
			'column' => 'eventTypeID',
			'property' => 'id',
			'type' => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column' => 'eventType',
			'property' => 'type',
			'type' => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column' => 'eventColour',
			'property' => 'color',
			'type' => self::TYPE_STRING,
		));
	}
} 