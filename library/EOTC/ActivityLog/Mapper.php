<?php
/**
 * ActivityLogMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC\ActivityLog;


class Mapper extends \Schoolbox\Mapper {

	protected function mapping() {
		$this->hasTable('eotc_activitylog');

		$this->hasMap(array(
			'column' => 'id',
			'property' => 'id',
			'type' => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column' => 'actioner_id',
			'property' => 'actioner',
			'type' => self::TYPE_MODEL,
			'join' => 'actioner',
		));
		$this->hasMap(array(
			'column' => 'event_id',
			'property' => 'event',
			'type' => self::TYPE_MODEL,
			'join' => 'event',
		));
		$this->hasMap(array(
			'column' => 'message',
			'property' => 'message',
			'type' => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'   => 'created_at',
			'property' => 'createdAt',
			'type'     => self::TYPE_DATETIME,
		));
		$this->hasMap(array(
			'column'   => 'updated_at',
			'property' => 'updatedAt',
			'type'     => self::TYPE_DATETIME,
		));
		$this->hasMap(array(
			'column'   => 'deleted_at',
			'property' => 'deletedAt',
			'type'     => self::TYPE_DATETIME,
		));
		$this->hasJoin('actioner', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'actioner_id',
			'other_key' => 'id',
		));
		$this->hasJoin('event', array(
			'mapper'    => '\\EOTC\\EventMapper',
			'this_key'  => 'event_id',
			'other_key' => 'id',
		));
		$this->hasJoin('teacherInCharge', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'teacher_in_charge_id',
			'other_key' => 'id',
		));
	}
} 