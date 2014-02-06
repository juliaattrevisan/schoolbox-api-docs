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
	 * Default status id
	 *
 	 * @var int
	 */
	private $defaultStatusId = 1;

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

	/**
	 * Find default status object
	 *
	 * @return object
	 */
	public function findDefaultStatus() {
		return $this->findById($this->getDefaultStatusId());
	}

	/* Getters/Setters
	 */

	/**
	 * Set default status id
	 * 
	 * @param int $defaultStatusId
	 */
	private function setDefaultStatusId($defaultStatusId) {
		$this->defaultStatusId = $defaultStatusId;
	}

	/**
	 * Get default status id
	 *
	 * @return int
	 */
	private function getDefaultStatusId() {
		return $this->defaultStatusId;
	}
}