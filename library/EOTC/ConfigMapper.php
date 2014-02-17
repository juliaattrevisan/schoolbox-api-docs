<?php
/**
 * ConfigMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;


class ConfigMapper extends \Schoolbox\Mapper {

	/**
	 * Mapping
	 */
	public function mapping() {
		$this->hasTable('eotc_config');
		$this->hasModel('\\EOTC\\Config');
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
		$this->hasMap(array(
			'column' => 'value',
			'property' => 'value',
			'type' => self::TYPE_STRING,
		));
	}

	/**
	 * Get urgent threshold
	 *
	 * @return \Schoolbox\Collection
	 */
	public function findUrgentThreshold() {
		return $this->findOneByColumn('name', 'urgent_threshold');
	}
}