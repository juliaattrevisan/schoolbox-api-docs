<?php
/**
 * EventPermissionMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;


class EventPermissionMapper extends \Schoolbox\Mapper {

	/**
	 * Mapping
	 */
	public function mapping() {
		$this->hasTable('eotc_permission');
		$this->hasModel('\\EOTC\\EventPermission');
		$this->hasMap(array(
			'column' => 'id',
			'property' => 'id',
			'type' => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column' => 'user_id',
			'property' => 'userId',
			'type' => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column' => 'campus_id',
			'property' => 'campusId',
			'type' => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column' => 'status_id',
			'property' => 'statusId',
			'type' => self::TYPE_INTEGER,
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
		$this->hasJoin('user', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'user_id',
			'other_key' => 'id',
		));
		$this->hasJoin('metadata', array(
			'mapper'    => '\\EOTC\\EventMetadataMapper',
			'this_key'  => 'metadata_id',
			'other_key' => 'id',
		));
		$this->hasJoin('campus', array(
			'mapper'    => '\\Schoolbox\\CampusMapper',
			'this_key'  => 'campus_id',
			'other_key' => 'id',
		));
		$this->hasJoin('status', array(
			'mapper'    => '\\EOTC\\EventStatusMapper',
			'this_key'  => 'status_id',
			'other_key' => 'id',
		));
	}
}