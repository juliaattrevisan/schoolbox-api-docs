<?php
/**
 * EventMetadataMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;


class EventMetadataMapper extends \Schoolbox\Mapper {

	/**
	 * Mapping
	 */
	public function mapping() {
		$this->hasTable('eotc_metadata');
		$this->hasModel('\\EOTC\\EventMetadata');
		$this->hasMap(array(
			'column' => 'id',
			'property' => 'id',
			'type' => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column' => 'event_type_id',
			'property' => 'eventType',
			'type' => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column' => 'status_id',
			'property' => 'status',
			'type' => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column' => 'rams_required',
			'property' => 'ramsRequired',
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
	}
} 