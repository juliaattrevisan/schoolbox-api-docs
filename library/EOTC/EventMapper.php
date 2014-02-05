<?php
/**
 * EventMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;


class EventMapper extends CalendarEventMapper {

	protected function mapping() {
//		$this->hasMap(array(
//			'column' => 'metadata',
//			'property' => 'metadata',
//			'type' => self::TYPE_INTEGER,
//			'join' => 'metadata',
//		));
//		$this->hasJoin('metadata', array(
//			'mapper'    => '\\EOTC\\EventMetadataMapper',
//			'this_key'  => 'metadata',
//			'other_key' => 'id',
//		));
	}
} 