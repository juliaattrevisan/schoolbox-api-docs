<?php
/**
 * EventStatusMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;


class EventStatusMapper extends \Schoolbox\Mapper {

	/**
	 * Mapping
	 */
	public function mapping() {
		$this->hasTable('eotc_status');
		$this->hasModel('\\EOTC\\EventStatus');
		$this->hasMap(array(
			'column' => 'id',
			'property' => 'id',
			'type' => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column' => 'name',
			'property' => 'name',
			'type' => self::TYPE_STRING,
		));
	}
}